<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\MessageHandler;

use PhpList\Core\Domain\Messaging\Message\PasswordResetMessage;
use PhpList\Core\Domain\Messaging\Service\EmailService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
class PasswordResetMessageHandler
{
    private EmailService $emailService;
    private string $passwordResetUrl;

    public function __construct(EmailService $emailService, string $passwordResetUrl)
    {
        $this->emailService = $emailService;
        $this->passwordResetUrl = $passwordResetUrl;
    }

    /**
     * Process a subscriber confirmation message by sending the confirmation email
     */
    public function __invoke(PasswordResetMessage $message): void
    {
        $confirmationLink = $this->generateLink($message->getToken());

        $subject = 'Password Reset Request';
        $textContent = "Hello,\n\n"
            . "A password reset has been requested for your account.\n"
            . "Please use the following token to reset your password:\n\n"
            . $message->getToken()
            . "\n\nIf you did not request this password reset, please ignore this email.\n\nThank you.";

        $htmlContent = '<p>Password Reset Request!</p>'
            . '<p>Hello! A password reset has been requested for your account.</p>'
            . '<p>Please use the following token to reset your password:</p>'
            . '<p><a href="' . $confirmationLink . '">Reset Password</a></p>'
            . '<p>If you did not request this password reset, please ignore this email.</p>'
            . '<p>Thank you.</p>';

        $email = (new Email())
            ->to($message->getEmail())
            ->subject($subject)
            ->text($textContent)
            ->html($htmlContent);

        $this->emailService->sendEmail($email);
    }

    private function generateLink(string $uniqueId): string
    {
        return $this->passwordResetUrl . '?uniqueId=' . urlencode($uniqueId);
    }
}
