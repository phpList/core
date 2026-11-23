<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Search\Command;

use DateTimeImmutable;
use PhpList\Core\Domain\Search\Client\ElasticsearchClientInterface;
use PhpList\Core\Domain\Search\Model\Interfaces\SearchIndexableInterface;
use PhpList\Core\Domain\Search\Model\Interfaces\SearchPurgeProviderInterface;
use PhpList\Core\Domain\Search\Registry\SearchPurgeProviderRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(
    name: 'phplist:search:purge',
    description: 'Delete DB rows older than a configured retention period, once confirmed to exist in Elasticsearch',
)]
class PurgeSearchIndexedRowsCommand extends Command
{
    private const DEFAULT_BATCH_SIZE = 500;

    public function __construct(
        private readonly SearchPurgeProviderRegistry $registry,
        private readonly ElasticsearchClientInterface $client,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('alias', InputArgument::OPTIONAL, 'Purge only this alias (default: all configured)')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Rows per batch', self::DEFAULT_BATCH_SIZE)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report counts without deleting anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $alias = $input->getArgument('alias');
        $batchSize = (int) $input->getOption('batch-size');
        $dryRun = (bool) $input->getOption('dry-run');

        if ($batchSize < 1) {
            $io->error($this->translator->trans('The --batch-size option must be greater than zero.'));
            return Command::FAILURE;
        }

        if ($alias !== null) {
            $provider = $this->registry->find($alias);

            if ($provider === null) {
                $io->error($this->translator->trans(
                    'No purge provider registered for alias "%alias%".',
                    ['%alias%' => $alias]
                ));
                return Command::FAILURE;
            }

            if ($provider->getRetentionPeriod() === null) {
                $io->error($this->translator->trans(
                    'No retention period configured for alias "%alias%".',
                    ['%alias%' => $alias]
                ));
                return Command::FAILURE;
            }

            $providers = [$provider];
        } else {
            $providers = array_filter(
                $this->registry->getAll(),
                static fn (SearchPurgeProviderInterface $provider): bool => $provider->getRetentionPeriod() !== null,
            );
        }

        if ($providers === []) {
            $io->warning($this->translator->trans('No purge providers with a configured retention period.'));
            return Command::SUCCESS;
        }

        foreach ($providers as $provider) {
            $this->purgeProvider($provider, $batchSize, $dryRun, $io);
        }

        return Command::SUCCESS;
    }

    private function purgeProvider(
        SearchPurgeProviderInterface $provider,
        int $batchSize,
        bool $dryRun,
        SymfonyStyle $io,
    ): void {
        $cutoff = (new DateTimeImmutable())->sub($provider->getRetentionPeriod());
        $io->writeln(sprintf(
            '<info>%s</info>: %s',
            $provider->getAlias(),
            $this->translator->trans(
                'purging rows older than %cutoff%',
                ['%cutoff%' => $cutoff->format(DateTimeImmutable::ATOM)]
            ),
        ));

        $lastId = 0;
        $scanned = 0;
        $deleted = 0;
        $skipped = [];

        do {
            $batch = [...$provider->fetchBatchOlderThan($cutoff, $lastId, $batchSize)];
            $countInBatch = count($batch);

            if ($countInBatch === 0) {
                break;
            }

            $result = $this->purgeBatch($provider, $batch, $dryRun);
            $deleted += $result['deleted'];
            array_push($skipped, ...$result['skipped']);
            $scanned += $countInBatch;
            $lastId = $result['lastId'];
        } while ($countInBatch >= $batchSize);

        $this->reportResults($provider, $scanned, $deleted, $skipped, $dryRun, $io);
    }

    /**
     * @param SearchIndexableInterface[] $batch
     * @return array{deleted: int, skipped: int[], lastId: int}
     */
    private function purgeBatch(SearchPurgeProviderInterface $provider, array $batch, bool $dryRun): array
    {
        $docIds = array_map(
            static fn (SearchIndexableInterface $entity): string => $entity->getSearchDocumentId(),
            $batch,
        );
        $confirmedIds = $this->confirmedInElasticsearch($provider->getSearchIndexName(), $docIds);

        $confirmedEntityIds = [];
        $skipped = [];
        foreach ($docIds as $docId) {
            if (in_array($docId, $confirmedIds, true)) {
                $confirmedEntityIds[] = (int) $docId;
            } else {
                $skipped[] = (int) $docId;
            }
        }

        $deleted = count($confirmedEntityIds);
        if (!$dryRun && $confirmedEntityIds !== []) {
            $deleted = $provider->deleteByIds($confirmedEntityIds);
        }

        return [
            'deleted' => $deleted,
            'skipped' => $skipped,
            'lastId' => (int) $docIds[array_key_last($docIds)],
        ];
    }

    /** @param int[] $skipped */
    private function reportResults(
        SearchPurgeProviderInterface $provider,
        int $scanned,
        int $deleted,
        array $skipped,
        bool $dryRun,
        SymfonyStyle $io,
    ): void {
        if ($skipped !== []) {
            $io->warning($this->translator->trans(
                '%alias%: %count% row(s) older than cutoff were not found in Elasticsearch '
                    . 'and were left in place: %ids%',
                [
                    '%alias%' => $provider->getAlias(),
                    '%count%' => count($skipped),
                    '%ids%' => implode(', ', array_slice($skipped, 0, 10)) . (count($skipped) > 10 ? ', ...' : ''),
                ]
            ));
        }

        $summary = $this->translator->trans(
            $dryRun
                ? '%alias%: scanned %scanned% row(s), would delete %count% row(s)'
                : '%alias%: scanned %scanned% row(s), deleted %count% row(s)',
            ['%alias%' => $provider->getAlias(), '%scanned%' => $scanned, '%count%' => $deleted]
        );
        if ($skipped !== []) {
            $summary .= $this->translator->trans(', skipped %count% unconfirmed', ['%count%' => count($skipped)]);
        }

        $io->success($summary . '.');
    }

    /** @param string[] $docIds @return string[] */
    private function confirmedInElasticsearch(string $indexName, array $docIds): array
    {
        if ($docIds === []) {
            return [];
        }

        $response = $this->client->search($indexName, [
            'size' => count($docIds),
            '_source' => false,
            'query' => [
                'bool' => [
                    'filter' => [
                        ['terms' => ['id' => array_map('intval', $docIds)]],
                    ],
                ],
            ],
        ]);

        return array_map(
            static fn (array $hit): string => (string) $hit['_id'],
            $response['hits']['hits'] ?? [],
        );
    }
}
