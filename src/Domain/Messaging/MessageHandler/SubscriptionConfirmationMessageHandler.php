<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\MessageHandler;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Service\UserPersonalizer;
use PhpList\Core\Domain\Messaging\Message\SubscriptionConfirmationMessage;
use PhpList\Core\Domain\Messaging\Service\EmailService;
use PhpList\Core\Domain\Subscription\Repository\SubscriberListRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

/**
 * Handler for processing asynchronous subscription confirmation email messages
 */
#[AsMessageHandler]
class SubscriptionConfirmationMessageHandler
{
    public function __construct(
        private readonly EmailService $emailService,
        private readonly ConfigProvider $configProvider,
        private readonly LoggerInterface $logger,
        private readonly UserPersonalizer $userPersonalizer,
        private readonly SubscriberListRepository $subscriberListRepository,
    ) {
    }

    /**
     * Process a subscription confirmation message by sending the confirmation email
     */
    public function __invoke(SubscriptionConfirmationMessage $message): void
    {
        $subject = $this->configProvider->getValue(ConfigOption::SubscribeEmailSubject);
        $textContent = $this->configProvider->getValue(ConfigOption::SubscribeMessage);
        $listOfLists = $this->getListNames($message->getListIds());
        $replacedTextContent = str_replace('[LISTS]', $listOfLists, $textContent);

        $personalizedTextContent = $this->userPersonalizer->personalize(
            value: $replacedTextContent,
            email: $message->getEmail(),
            format: OutputFormat::Text,
        );

        $email = (new Email())
            ->to($message->getEmail())
            ->subject($subject)
            ->text($personalizedTextContent);

        $this->emailService->sendEmail($email);

        $this->logger->info('Subscription confirmation email sent to {email}', ['email' => $message->getEmail()]);
    }

    private function getListNames(array $listIds): string
    {
        return implode(', ', $this->subscriberListRepository->getListNames($listIds));
    }
}
