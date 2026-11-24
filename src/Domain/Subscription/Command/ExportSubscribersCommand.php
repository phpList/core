<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Command;

use PhpList\Core\Domain\Subscription\Model\Filter\SubscriberFilter;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeDefinitionRepository;
use PhpList\Core\Domain\Subscription\Service\SubscriberCsvExporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Equivalent of phplist3's `phplist -p export [-l list]`: exports all columns and attributes of all
 * subscribers, or of the subscribers of one list, as CSV. Writes to stdout unless --output is given.
 */
#[AsCommand(
    name: 'phplist:export-subscribers',
    description: 'Exports subscribers to CSV (stdout, or a file with --output).'
)]
class ExportSubscribersCommand extends Command
{
    private const BASE_COLUMNS = [
        'id', 'email', 'confirmed', 'blacklisted', 'bounceCount', 'createdAt', 'updatedAt',
        'uniqueId', 'htmlEmail', 'rssFrequency', 'disabled', 'extraData', 'foreignKey',
    ];

    public function __construct(
        private readonly SubscriberCsvExporter $exporter,
        private readonly SubscriberAttributeDefinitionRepository $definitionRepository,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('list', 'l', InputOption::VALUE_REQUIRED, 'Only export subscribers of this list id')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Write the CSV to this file instead of stdout');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $list = $input->getOption('list');
        $target = $input->getOption('output');
        $error = $this->validate($list, $target);
        if ($error !== null) {
            $output->writeln($error);

            return Command::INVALID;
        }

        try {
            $response = $this->exporter->exportToCsv(
                new SubscriberFilter(listId: $list !== null ? (int) $list : null, columns: $this->allColumns())
            );
        } catch (Throwable $throwable) {
            $output->writeln($throwable->getMessage());

            return Command::FAILURE;
        }

        if (!$response instanceof BinaryFileResponse) {
            $output->writeln($this->translator->trans('Export failed.'));

            return Command::FAILURE;
        }

        $path = $response->getFile()->getPathname();
        try {
            return $target !== null
                ? $this->copyToFile($path, (string) $target, $output)
                : $this->streamToOutput($path, $output);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function validate(mixed $list, mixed $target): ?string
    {
        if ($list !== null && (!ctype_digit((string) $list) || (int) $list < 1)) {
            return $this->translator->trans('The list option must be a positive integer.');
        }
        if ($target !== null && !is_writable(dirname((string) $target))) {
            return $this->translator->trans('Cannot write to %file%', ['%file%' => $target]);
        }

        return null;
    }

    /** @return string[] */
    private function allColumns(): array
    {
        $columns = self::BASE_COLUMNS;
        foreach ($this->definitionRepository->findAll() as $definition) {
            $columns[] = $definition->getName();
        }

        return $columns;
    }

    private function copyToFile(string $path, string $target, OutputInterface $output): int
    {
        if (!copy($path, $target)) {
            return Command::FAILURE;
        }
        $output->writeln($this->translator->trans('Exported subscribers to %file%', ['%file%' => $target]));

        return Command::SUCCESS;
    }

    private function streamToOutput(string $path, OutputInterface $output): int
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return Command::FAILURE;
        }
        while (!feof($handle)) {
            $output->write((string) fread($handle, 65536), false, OutputInterface::OUTPUT_RAW);
        }
        fclose($handle);

        return Command::SUCCESS;
    }
}
