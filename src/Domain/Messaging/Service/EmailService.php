<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

class EmailService
{
    private MailerInterface $mailer;
    private string $defaultFromEmail;

    public function __construct(MailerInterface $mailer, string $defaultFromEmail)
    {
        $this->mailer = $mailer;
        $this->defaultFromEmail = $defaultFromEmail;
    }

    /**
     * Send a simple email
     *
     * @param Email $email
     * @param array $cc
     * @param array $bcc
     * @param array $replyTo
     * @param array $attachments
     * @return void
     * @throws TransportExceptionInterface
     */
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

    /**
     * Email multiple recipients
     *
     * @param array $toAddresses Array of recipient email addresses
     * @param string $subject Email subject
     * @param string $text Plain text content
     * @param string $html HTML content (optional)
     * @param string|null $from Sender email address (optional, uses default if not provided)
     * @param string|null $fromName Sender name (optional)
     * @param array $attachments Array of file paths to attach (optional)
     *
     * @return void
     * @throws TransportExceptionInterface
     */
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
}
