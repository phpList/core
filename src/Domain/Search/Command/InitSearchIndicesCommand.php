<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Search\Command;

use PhpList\Core\Domain\Search\Registry\SearchIndexDefinitionRegistry;
use PhpList\Core\Domain\Search\Service\ElasticsearchIndexerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(
    name: 'phplist:search:init-indices',
    description: 'Create or update Elasticsearch indices for all registered searchable entities',
)]
class InitSearchIndicesCommand extends Command
{
    public function __construct(
        private readonly SearchIndexDefinitionRegistry $registry,
        private readonly ElasticsearchIndexerInterface $indexer,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'index',
            null,
            InputOption::VALUE_REQUIRED,
            'Only create/update the index for this alias (e.g. subscriber_history)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $alias = $input->getOption('index');

        $definitions = $alias !== null
            ? array_filter([$this->registry->find($alias)])
            : $this->registry->getAll();

        if ($definitions === []) {
            $io->warning($alias !== null
                ? $this->translator->trans('No index definition registered for alias "%alias%".', ['%alias%' => $alias])
                : $this->translator->trans('No index definitions registered.'));

            return Command::SUCCESS;
        }

        foreach ($definitions as $definition) {
            $this->indexer->createOrUpdateIndex($definition);
            $io->writeln(sprintf('<info>OK</info> %s', $definition->getIndexAlias()));
        }

        return Command::SUCCESS;
    }
}
