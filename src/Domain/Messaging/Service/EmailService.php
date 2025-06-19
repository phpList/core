<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Messaging\Message\AsyncEmailMessage;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

class EmailService
{
    private MailerInterface $mailer;
    private string $defaultFromEmail;
    private MessageBusInterface $messageBus;

    public function __construct(
        MailerInterface $mailer,
        string $defaultFromEmail,
        MessageBusInterface $messageBus
    ) {
        $this->mailer = $mailer;
        $this->defaultFromEmail = $defaultFromEmail;
        $this->messageBus = $messageBus;
    }

    public function sendEmail(
        Email $email,
        array $cc = [],
        array $bcc = [],
        array $replyTo = [],
        array $attachments = []
    ): void {
        if (count($email->getFrom()) === 0) {
            $email->from($this->defaultFromEmail);
        }

        $message = new AsyncEmailMessage($email, $cc, $bcc, $replyTo, $attachments);
        $this->messageBus->dispatch($message);
    }

    public function sendEmailSync(
        Email $email,
        array $cc = [],
        array $bcc = [],
        array $replyTo = [],
        array $attachments = []
    ): void {
        if (count($email->getFrom()) === 0) {
            $email->from($this->defaultFromEmail);
        }

        foreach ($cc as $ccAddress) {
            $email->addCc($ccAddress);
        }

        foreach ($bcc as $bccAddress) {
            $email->addBcc($bccAddress);
        }

        foreach ($replyTo as $replyToAddress) {
            $email->addReplyTo($replyToAddress);
        }

        foreach ($attachments as $attachment) {
            $email->attachFromPath($attachment);
        }

        $this->mailer->send($email);
    }

    public function sendBulkEmail(
        array $toAddresses,
        string $subject,
        string $text,
        string $html = '',
        ?string $from = null,
        ?string $fromName = null,
        array $attachments = []
    ): void {
        $baseEmail = (new Email())
            ->subject($subject)
            ->text($text)
            ->html($html);

        if ($from) {
            $baseEmail->from($fromName ? new Address($from, $fromName) : $from);
        }

        foreach ($toAddresses as $recipient) {
            $email = clone $baseEmail;
            $email->to($recipient);

            $this->sendEmail($email, [], [], [], $attachments);
        }
    }

    public function sendBulkEmailSync(
        array $toAddresses,
        string $subject,
        string $text,
        string $html = '',
        ?string $from = null,
        ?string $fromName = null,
        array $attachments = []
    ): void {
        $baseEmail = (new Email())
            ->subject($subject)
            ->text($text)
            ->html($html);

        if ($from) {
            $baseEmail->from($fromName ? new Address($from, $fromName) : $from);
        }

        foreach ($toAddresses as $recipient) {
            $email = clone $baseEmail;
            $email->to($recipient);

            $this->sendEmailSync($email, [], [], [], $attachments);
        }
    }
}
