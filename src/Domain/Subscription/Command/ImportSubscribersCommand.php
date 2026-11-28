<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Command;

use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Repository\AdministratorRepository;
use PhpList\Core\Domain\Subscription\Model\Dto\SubscriberImportOptions;
use PhpList\Core\Domain\Subscription\Service\SubscriberCsvImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Equivalent of phplist3's `phplist -p import -f file.csv -l 1,2`.
 *
 * Like the legacy CLI import: existing subscribers are updated, invalid emails are skipped and no
 * confirmation emails are sent. Deviations: --dry-run is available, and --notify opts in to sending
 * confirmations (the legacy CLI never did).
 */
#[AsCommand(
    name: 'phplist:import-subscribers',
    description: 'Imports subscribers from a CSV file into the given lists.'
)]
class ImportSubscribersCommand extends Command
{
    public function __construct(
        private readonly SubscriberCsvImporter $importer,
        private readonly AdministratorRepository $administratorRepository,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Path of the CSV file to import')
            ->addOption('list', 'l', InputOption::VALUE_REQUIRED, 'Comma separated list ids to add subscribers to')
            ->addOption('admin', null, InputOption::VALUE_REQUIRED, 'Id of the administrator recorded in the history')
            ->addOption('no-update', null, InputOption::VALUE_NONE, 'Skip subscribers that already exist')
            ->addOption('notify', null, InputOption::VALUE_NONE, 'Send confirmation requests to new subscribers')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Process the file but do not save anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = (string) $input->getOption('file');
        if ($file === '' || !is_file($file) || !is_readable($file)) {
            $output->writeln($this->translator->trans('Cannot find file to import (hint: use -f)'));

            return Command::INVALID;
        }

        $listIds = array_values(array_unique(array_map(
            'intval',
            array_filter(
                array_map('trim', explode(',', (string) $input->getOption('list'))),
                static fn (string $id): bool => ctype_digit($id) && (int) $id > 0
            )
        )));
        if ($listIds === []) {
            $output->writeln($this->translator->trans('Specify lists to import subscribers to (hint: use -l 1,2)'));

            return Command::INVALID;
        }

        $admin = $this->resolveAdmin($input->getOption('admin'));
        if ($admin === false) {
            $output->writeln($this->translator->trans('Administrator not found.'));

            return Command::INVALID;
        }

        $stats = $this->importer->importFromCsv(
            file: new UploadedFile($file, basename($file), null, null, true),
            options: new SubscriberImportOptions(
                updateExisting: !$input->getOption('no-update'),
                listIds: $listIds,
                dryRun: (bool) $input->getOption('dry-run'),
                notify: (bool) $input->getOption('notify'),
            ),
            admin: $admin,
        );

        foreach ($stats['errors'] as $error) {
            $output->writeln($error);
        }
        $output->writeln($this->translator->trans(
            'Created: %created%, updated: %updated%, skipped: %skipped%, blacklisted: %blacklisted%',
            [
                '%created%' => $stats['created'],
                '%updated%' => $stats['updated'],
                '%skipped%' => $stats['skipped'],
                '%blacklisted%' => $stats['blacklisted'],
            ]
        ));

        return Command::SUCCESS;
    }

    /** @return Administrator|false|null false when an id was given but not found */
    private function resolveAdmin(mixed $adminId): Administrator|false|null
    {
        if ($adminId === null) {
            return null;
        }

        return ctype_digit((string) $adminId)
            ? ($this->administratorRepository->find((int) $adminId) ?? false)
            : false;
    }
}
