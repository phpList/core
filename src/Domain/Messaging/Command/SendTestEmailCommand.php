<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Command;

use Exception;
use PhpList\Core\Domain\Messaging\Model\Dto\EmailMessage;
use PhpList\Core\Domain\Messaging\Service\EmailService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class SendTestEmailCommand extends Command
{
    protected static $defaultName = 'app:send-test-email';
    protected static $defaultDescription = 'Send a test email to verify email configuration';

    private EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        parent::__construct();
        $this->emailService = $emailService;
    }

    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addArgument('recipient', InputArgument::OPTIONAL, 'Recipient email address');
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
            $output->writeln('Sending test email to ' . $recipient);

            $email = (new Email())
                ->from(new Address('admin@example.com', 'Admin Team'))
                ->to($recipient)
                ->subject('Test Email from phpList')
                ->text('This is a test email sent from phpList Email Service.')
                ->html('<h1>Test</h1><p>This is a <strong>test email</strong> sent from phpList Email Service</p>');
            
            $this->emailService->sendEmail($email);
            $output->writeln('Test email sent successfully!');

            return Command::SUCCESS;
        } catch (Exception $e) {
            $output->writeln('Failed to send test email: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
