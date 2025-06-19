<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\MessageHandler;

use PhpList\Core\Domain\Messaging\Message\SubscriberConfirmationMessage;
use PhpList\Core\Domain\Messaging\Service\EmailService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

/**
 * Handler for processing asynchronous subscriber confirmation email messages
 */
#[AsMessageHandler]
class SubscriberConfirmationMessageHandler
{
    private EmailService $emailService;
    private string $confirmationUrl;

    public function __construct(EmailService $emailService, string $confirmationUrl)
    {
        $this->emailService = $emailService;
        $this->confirmationUrl = $confirmationUrl;
    }

    /**
     * Process a subscriber confirmation message by sending the confirmation email
     */
    public function __invoke(SubscriberConfirmationMessage $message): void
    {
        $confirmationLink = $this->generateConfirmationLink($message->getUniqueId());

        $subject = 'Please confirm your subscription';
        $textContent = "Thank you for subscribing!\n\n"
            . "Please confirm your subscription by clicking the link below:\n"
            . $confirmationLink . "\n\n"
            . "If you did not request this subscription, please ignore this email.";

        $htmlContent = '';
        if ($message->hasHtmlEmail()) {
            $htmlContent = "<p>Thank you for subscribing!</p>"
                . "<p>Please confirm your subscription by clicking the link below:</p>"
                . "<p><a href=\"" . $confirmationLink . "\">Confirm Subscription</a></p>"
                . "<p>If you did not request this subscription, please ignore this email.</p>";
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
        return $this->confirmationUrl . $uniqueId;
    }
}
