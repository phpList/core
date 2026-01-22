<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\MessageHandler;

use PhpList\Core\Domain\Messaging\Message\PasswordResetMessage;
use PhpList\Core\Domain\Messaging\Service\EmailService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
class PasswordResetMessageHandler
{
    public function __construct(
        private readonly EmailService $emailService,
        private readonly TranslatorInterface $translator,
        #[Autowire('%app.password_reset_url%')] private readonly string $passwordResetUrl
    ) {
    }

    /**
     * Process a subscriber confirmation message by sending the confirmation email
     */
    public function __invoke(PasswordResetMessage $message): void
    {
        $confirmationLink = $this->generateLink($message->getToken());

        $subject = $this->translator->trans('Password Reset Request');

        $textContent = $this->translator->trans(
            "Hello,\n\n" .
            "A password reset has been requested for your account.\n" .
            "Please use the following token to reset your password:\n\n" .
            "%token%\n\n" .
            "If you did not request this password reset, please ignore this email.\n\n" .
            'Thank you.',
            ['%token%' => $message->getToken()]
        );

        $htmlContent = $this->translator->trans(
            '<p>Password Reset Request!</p>' .
            '<p>Hello! A password reset has been requested for your account.</p>' .
            '<p>Please use the following token to reset your password:</p>' .
            '<p><a href="%confirmation_link%">Reset Password</a></p>' .
            '<p>If you did not request this password reset, please ignore this email.</p>' .
            '<p>Thank you.</p>',
            [
                '%confirmation_link%' => $confirmationLink,
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
