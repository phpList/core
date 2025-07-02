<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Command;

use Exception;
use PhpList\Core\Domain\Messaging\Service\EmailService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'phplist:test-email',
    description: 'Send a test email to verify email configuration'
)]
class SendTestEmailCommand extends Command
{
    private EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        parent::__construct();
        $this->emailService = $emailService;
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                name: 'recipient',
                mode: InputArgument::OPTIONAL,
                description: 'Recipient email address'
            )
            ->addOption(
                name: 'sync',
                mode: InputArgument::OPTIONAL,
                description: 'Send email synchronously instead of queuing it',
                default: false
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $recipient = $input->getArgument('recipient');
        if (!$recipient) {
            $output->writeln('Recipient email address not provided');

            return Command::FAILURE;
        }

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $output->writeln('Invalid email address: ' . $recipient);

            return Command::FAILURE;
        }

        try {
            $syncMode = $input->getOption('sync');

            if ($syncMode) {
                $output->writeln('Sending test email synchronously to ' . $recipient);
            } else {
                $output->writeln('Queuing test email for ' . $recipient);
            }

            $email = (new Email())
                ->from(new Address('admin@example.com', 'Admin Team'))
                ->to($recipient)
                ->subject('Test Email from phpList')
                ->text('This is a test email sent from phpList Email Service.')
                ->html('<h1>Test</h1><p>This is a <strong>test email</strong> sent from phpList Email Service</p>');

            if ($syncMode) {
                $this->emailService->sendEmailSync($email);
                $output->writeln('Test email sent successfully!');
            } else {
                $this->emailService->sendEmail($email);
                $output->writeln('Test email queued successfully! It will be sent asynchronously.');
            }

            return Command::SUCCESS;
        } catch (Exception $e) {
            $output->writeln('Failed to send test email: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
