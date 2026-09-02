<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Search\Command;

use PhpList\Core\Domain\Search\Model\Interfaces\SearchReindexProviderInterface;
use PhpList\Core\Domain\Search\Registry\SearchReindexProviderRegistry;
use PhpList\Core\Domain\Search\Service\ElasticsearchIndexerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'phplist:search:reindex',
    description: 'Bulk backfill Elasticsearch from the database for one or all registered searchable entities',
)]
class ReindexSearchCommand extends Command
{
    private const DEFAULT_BATCH_SIZE = 500;

    public function __construct(
        private readonly SearchReindexProviderRegistry $registry,
        private readonly ElasticsearchIndexerInterface $indexer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('alias', InputArgument::OPTIONAL, 'Reindex only this alias (default: all registered)')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Rows per batch', self::DEFAULT_BATCH_SIZE)
            ->addOption(
                'last-id',
                null,
                InputOption::VALUE_REQUIRED,
                'Resume from this id (only meaningful with a single alias)',
                0,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $alias = $input->getArgument('alias');
        $batchSize = (int) $input->getOption('batch-size');
        $lastId = (int) $input->getOption('last-id');

        $providers = $alias !== null
            ? array_filter([$this->registry->find($alias)])
            : $this->registry->getAll();

        if ($providers === []) {
            $io->warning($alias !== null
                ? sprintf('No reindex provider registered for alias "%s".', $alias)
                : 'No reindex providers registered.');

            return Command::SUCCESS;
        }

        foreach ($providers as $provider) {
            $this->reindexProvider($provider, $lastId, $batchSize, $io);
        }

        return Command::SUCCESS;
    }

    private function reindexProvider(
        SearchReindexProviderInterface $provider,
        int $lastId,
        int $batchSize,
        SymfonyStyle $io,
    ): void {
        $total = $provider->countAll();
        $io->writeln(sprintf('<info>%s</info>: %d rows total', $provider->getAlias(), $total));
        $progressBar = $io->createProgressBar($total);

        $indexed = 0;
        do {
            $batch = $provider->fetchBatch($lastId, $batchSize);
            $countInBatch = 0;

            foreach ($batch as $entity) {
                $this->indexer->index(
                    $entity->getSearchIndexName(),
                    $entity->getSearchDocumentId(),
                    $entity->toSearchDocument(),
                    (int) (microtime(true) * 1_000_000),
                );
                $lastId = (int) $entity->getSearchDocumentId();
                $countInBatch++;
                $indexed++;
            }

            $progressBar->setProgress($indexed);
        } while ($countInBatch >= $batchSize);

        $progressBar->finish();
        $io->newLine(2);
        $io->success(sprintf('%s: indexed %d rows.', $provider->getAlias(), $indexed));
    }
}
