<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\MessageHandler;

use PhpList\Core\Domain\Messaging\Message\PasswordResetMessage;
use PhpList\Core\Domain\Messaging\Service\EmailService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
class PasswordResetMessageHandler
{
    private EmailService $emailService;
    private TranslatorInterface $translator;
    private string $passwordResetUrl;

    public function __construct(EmailService $emailService, TranslatorInterface $translator, string $passwordResetUrl)
    {
        $this->emailService = $emailService;
        $this->translator = $translator;
        $this->passwordResetUrl = $passwordResetUrl;
    }

    /**
     * Process a subscriber confirmation message by sending the confirmation email
     */
    public function __invoke(PasswordResetMessage $message): void
    {
        $confirmationLink = $this->generateLink($message->getToken());

        $subject = $this->translator->trans('Password Reset Request');
        $textContent = $this->translator->trans(
            <<<TXT
            Hello,
                
            A password reset has been requested for your account.
            Please use the following token to reset your password:
            
            %token%
            
            If you did not request this password reset, please ignore this email.
            
            Thank you.
            TXT,
            ['%token%' => $message->getToken()]
        );

        $htmlContent = $this->translator->trans(
            <<<HTML
<p>Password Reset Request!</p>
<p>Hello! A password reset has been requested for your account.</p>
<p>Please use the following token to reset your password:</p>
<p><a href="%confirmationLink%">Reset Password</a></p>
<p>If you did not request this password reset, please ignore this email.</p>
<p>Thank you.</p>
HTML,
            [
                '%token%' => $message->getToken(),
                '%confirmationLink%' => $confirmationLink,
            ]
        );

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
