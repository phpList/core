<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Command;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PhpList\Core\Domain\Messaging\Service\CliCampaignCreator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Equivalent of phplist3's `phplist -p send -s subject -l list [-f from] < message`:
 * creates a campaign from stdin, attaches it to the lists and queues it.
 *
 * Deviations from phplist3: unknown lists are an error (legacy silently skipped them), and the
 * campaign owner is --admin or else the first administrator (legacy had no owner on the CLI).
 */
#[AsCommand(
    name: 'phplist:send',
    description: 'Creates a campaign from stdin and queues it for the given lists.'
)]
class SendCommand extends Command
{
    public function __construct(
        private readonly CliCampaignCreator $campaignCreator,
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('subject', 's', InputOption::VALUE_REQUIRED, 'Campaign subject')
            ->addOption('list', 'l', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'List id or name')
            ->addOption('from', 'f', InputOption::VALUE_REQUIRED, 'From field, defaults to the configured sender')
            ->addOption('admin', null, InputOption::VALUE_REQUIRED, 'Id of the owning administrator');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $subject = trim((string) $input->getOption('subject'));
        $lists = array_values(
            array_filter(
                array_map('trim', explode(' ', implode(' ', (array) $input->getOption('list')))),
                static fn (string $list): bool => $list !== ''
            )
        );
        $admin = $input->getOption('admin');

        if ($subject === '' || $lists === []) {
            $output->writeln($this->translator->trans('Usage: -s subject -l list [-f from] < message'));

            return Command::INVALID;
        }
        if ($admin !== null && !ctype_digit((string) $admin)) {
            $output->writeln($this->translator->trans('The admin option must be a positive integer.'));

            return Command::INVALID;
        }

        $body = $this->readBody($input);
        if (trim($body) === '') {
            $output->writeln($this->translator->trans('The message body is empty, pipe it in on stdin.'));

            return Command::INVALID;
        }

        try {
            $campaign = $this->campaignCreator->create(
                subject: $subject,
                body: $body,
                listReferences: $lists,
                from: $input->getOption('from'),
                adminId: $admin !== null ? (int) $admin : null,
            );
            $this->entityManager->flush();
        } catch (InvalidArgumentException $exception) {
            $output->writeln($exception->getMessage());

            return Command::FAILURE;
        } catch (Throwable $throwable) {
            $output->writeln($throwable->getMessage());

            return Command::FAILURE;
        }

        $output->writeln(
            $this->translator->trans(
                'Campaign "%subject%" (id %id%) was queued for lists: %lists%',
                ['%subject%' => $subject, '%id%' => $campaign->getId(), '%lists%' => implode(', ', $lists)]
            )
        );

        return Command::SUCCESS;
    }

    private function readBody(InputInterface $input): string
    {
        $stream = $input instanceof StreamableInputInterface ? $input->getStream() : null;
        $contents = stream_get_contents($stream ?? STDIN);

        return $contents === false ? '' : $contents;
    }
}
