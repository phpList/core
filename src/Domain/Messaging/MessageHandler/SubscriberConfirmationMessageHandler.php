<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\MessageHandler;

use PhpList\Core\Domain\Messaging\Message\SubscriberConfirmationMessage;
use PhpList\Core\Domain\Messaging\Service\EmailService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handler for processing asynchronous subscriber confirmation email messages
 */
#[AsMessageHandler]
class SubscriberConfirmationMessageHandler
{
    private EmailService $emailService;
    private TranslatorInterface $translator;
    private string $confirmationUrl;

    public function __construct(EmailService $emailService, TranslatorInterface $translator, string $confirmationUrl)
    {
        $this->emailService = $emailService;
        $this->translator = $translator;
        $this->confirmationUrl = $confirmationUrl;
    }

    /**
     * Process a subscriber confirmation message by sending the confirmation email
     */
    public function __invoke(SubscriberConfirmationMessage $message): void
    {
        $confirmationLink = $this->generateConfirmationLink($message->getUniqueId());

        $subject = $this->translator->trans('Please confirm your subscription');

        $textContent = $this->translator->trans(
            <<<TXT
            Thank you for subscribing!
                
            Please confirm your subscription by clicking the link below:
            
            %confirmation_link%
            
            If you did not request this subscription, please ignore this email.            
            TXT,
            [
                '%confirmation_link%' => $confirmationLink
            ]
        );

        $htmlContent = '';
        if ($message->hasHtmlEmail()) {
            $htmlContent = $this->translator->trans(
                <<<HTML
<p>Thank you for subscribing!</p>
<p>Please confirm your subscription by clicking the link below:</p>
<p><a href="%confirmation_link%">Confirm Subscription</a></p>
<p>If you did not request this subscription, please ignore this email.</p>
HTML,
                [
                    '%confirmation_link%' => $confirmationLink,
                ]
            );
        }

        $email = (new Email())
            ->to($message->getEmail())
            ->subject($subject)
            ->text($textContent);

        if (!empty($htmlContent)) {
            $email->html($htmlContent);
        }

        $this->emailService->sendEmail($email);
    }

    /**
     * Generate a confirmation link for the subscriber
     */
    private function generateConfirmationLink(string $uniqueId): string
    {
        return $this->confirmationUrl . '?uniqueId=' . urlencode($uniqueId);
    }
}
