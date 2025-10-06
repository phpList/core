<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\MessageHandler;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
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
    private EmailService $emailService;
    private ConfigProvider $configProvider;
    private LoggerInterface $logger;
    private UserPersonalizer $userPersonalizer;
    private SubscriberListRepository $subscriberListRepository;

    public function __construct(
        EmailService $emailService,
        ConfigProvider $configProvider,
        LoggerInterface $logger,
        UserPersonalizer $userPersonalizer,
        SubscriberListRepository $subscriberListRepository,
    ) {
        $this->emailService = $emailService;
        $this->configProvider = $configProvider;
        $this->logger = $logger;
        $this->userPersonalizer = $userPersonalizer;
        $this->subscriberListRepository = $subscriberListRepository;
    }

    /**
     * Process a subscription confirmation message by sending the confirmation email
     */
    public function __invoke(SubscriptionConfirmationMessage $message): void
    {
        $subject = $this->configProvider->getValue(ConfigOption::SubscribeEmailSubject);
        $textContent = $this->configProvider->getValue(ConfigOption::SubscribeMessage);
        $personalizedTextContent = $this->userPersonalizer->personalize($textContent, $message->getUniqueId());
        $listOfLists = $this->getListNames($message->getListIds());
        $replacedTextContent = str_replace('[LISTS]', $personalizedTextContent, $listOfLists);

        $email = (new Email())
            ->to($message->getEmail())
            ->subject($subject)
            ->text($replacedTextContent);

        $this->emailService->sendEmail($email);

        $this->logger->info('Subscription confirmation email sent to {email}', ['email' => $message->getEmail()]);
    }

    private function getListNames(array $listIds): string
    {
        $listNames = [];
        foreach ($listIds as $id) {
            $list = $this->subscriberListRepository->find($id);
            if ($list) {
                $listNames[] = $list->getName();
            }
        }

        return implode(', ', $listNames);
    }
}
