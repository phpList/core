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
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'phplist:test-email',
    description: 'Send a test email to verify email configuration'
)]
class SendTestEmailCommand extends Command
{
    private EmailService $emailService;
    private TranslatorInterface $translator;

    public function __construct(EmailService $emailService, TranslatorInterface $translator)
    {
        parent::__construct();
        $this->emailService = $emailService;
        $this->translator = $translator;
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
            $output->writeln($this->translator->trans('Recipient email address not provided'));

            return Command::FAILURE;
        }

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $output->writeln($this->translator->trans('Invalid email address: %email%', ['%email%' => $recipient]));

            return Command::FAILURE;
        }

        try {
            $syncMode = $input->getOption('sync');

            if ($syncMode) {
                $output->writeln($this->translator->trans(
                    'Sending test email synchronously to %email%',
                    ['%email%' => $recipient]
                ));
            } else {
                $output->writeln($this->translator->trans(
                    'Queuing test email for %email%',
                    ['%email%' => $recipient]
                ));
            }

            $email = (new Email())
                ->from(new Address('admin@example.com', 'Admin Team'))
                ->to($recipient)
                ->subject('Test Email from phpList')
                ->text('This is a test email sent from phpList Email Service.')
                ->html('<h1>Test</h1><p>This is a <strong>test email</strong> sent from phpList Email Service</p>');

            if ($syncMode) {
                $this->emailService->sendEmailSync($email);
                $output->writeln($this->translator->trans('Test email sent successfully!'));
            } else {
                $this->emailService->sendEmail($email);
                $output->writeln($this->translator->trans(
                    'Test email queued successfully! It will be sent asynchronously.'
                ));
            }

            return Command::SUCCESS;
        } catch (Exception $e) {
            $output->writeln($this->translator->trans(
                'Failed to send test email: %error%',
                ['%error%' => $e->getMessage()]
            ));

            return Command::FAILURE;
        }
    }
}
