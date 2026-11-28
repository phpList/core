<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\MessageHandler;

use PhpList\Core\Domain\Messaging\Message\SubscriberConfirmationMessage;
use PhpList\Core\Domain\Messaging\Service\EmailService;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Resolver\SubscriberLocaleResolver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handler for processing asynchronous subscriber confirmation email messages
 */
#[AsMessageHandler]
class SubscriberConfirmationMessageHandler
{
    public function __construct(
        private readonly EmailService $emailService,
        private readonly TranslatorInterface $translator,
        private readonly SubscriberRepository $subscriberRepository,
        private readonly SubscriberLocaleResolver $localeResolver,
        #[Autowire('%app.confirmation_url%')]private readonly string $confirmationUrl
    ) {
    }

    /**
     * Process a subscriber confirmation message by sending the confirmation email
     */
    public function __invoke(SubscriberConfirmationMessage $message): void
    {
        $confirmationLink = $this->generateConfirmationLink($message->getUniqueId());

        $locale = $this->resolveLocale($message);

        $subject = $this->translator->trans('Please confirm your subscription', [], null, $locale);

        $textContent = $this->translator->trans(
            "Thank you for subscribing!\n\n" .
            "Please confirm your subscription by clicking the link below:\n\n" .
            "%confirmation_link%\n\n" .
            'If you did not request this subscription, please ignore this email.',
            [
                '%confirmation_link%' => $confirmationLink
            ],
            null,
            $locale
        );

        $htmlContent = '';
        if ($message->hasHtmlEmail()) {
            $htmlContent = $this->translator->trans(
                '<p>Thank you for subscribing!</p>' .
                '<p>Please confirm your subscription by clicking the link below:</p>' .
                '<p><a href="%confirmation_link%">Confirm Subscription</a></p>' .
                '<p>If you did not request this subscription, please ignore this email.</p>',
                [
                    '%confirmation_link%' => $confirmationLink,
                ],
                null,
                $locale
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
     * phplist3 has no per-subscriber language: it uses the language of the subscribe page the subscriber used.
     * A locale set explicitly on the message wins, otherwise it is resolved from that page (default locale as
     * fallback), since the request locale is not available in a queue worker.
     */
    private function resolveLocale(SubscriberConfirmationMessage $message): ?string
    {
        if ($message->getLocale() !== null) {
            return $message->getLocale();
        }

        $subscriber = $this->subscriberRepository->findOneByUniqueId($message->getUniqueId());

        return $subscriber === null ? null : $this->localeResolver->resolve($subscriber);
    }

    /**
     * Generate a confirmation link for the subscriber
     */
    private function generateConfirmationLink(string $uniqueId): string
    {
        return $this->confirmationUrl . '?uniqueId=' . urlencode($uniqueId);
    }
}
