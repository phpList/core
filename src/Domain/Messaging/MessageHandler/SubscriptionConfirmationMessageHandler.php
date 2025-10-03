<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\MessageHandler;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Service\UserPersonalizer;
use PhpList\Core\Domain\Messaging\Message\SubscriberConfirmationMessage;
use PhpList\Core\Domain\Messaging\Service\EmailService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

/**
 * Handler for processing asynchronous subscription confirmation email messages
 */
#[AsMessageHandler]
class SubscriptionConfirmationMessageHandler
{
    private EmailService $emailService;
    private ConfigProvider $configProvider;
    private LoggerInterface $logger;
    private UserPersonalizer $userPersonalizer;

    public function __construct(
        EmailService $emailService,
        ConfigProvider $configProvider,
        LoggerInterface $logger,
        UserPersonalizer $userPersonalizer,
    ) {
        $this->emailService = $emailService;
        $this->configProvider = $configProvider;
        $this->logger = $logger;
        $this->userPersonalizer = $userPersonalizer;
    }

    /**
     * Process a subscription confirmation message by sending the confirmation email
     */
    public function __invoke(SubscriberConfirmationMessage $message): void
    {
        $subject = $this->configProvider->getValue(ConfigOption::SubscribeEmailSubject);
        $textContent = $this->configProvider->getValue(ConfigOption::SubscribeMessage);
        $replacedTextContent = $this->userPersonalizer->personalize($textContent, $message->getUniqueId());

        $email = (new Email())
            ->to($message->getEmail())
            ->subject($subject)
            ->text($replacedTextContent);

        $this->emailService->sendEmail($email);

        $this->logger->info('Subscription confirmation email sent to {email}', ['email' => $message->getEmail()]);
    }
}
