<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Service;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Analytics\Model\UserMessageView;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;

class UserMessageService
{
    public function __construct(
        private readonly UserMessageRepository $userMessageRepository,
        private readonly SubscriberRepository $subscriberRepository,
        private readonly MessageRepository $messageRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function trackUserMessageView(string $uid, int $messageId, array $metadata): void
    {
        $subscriber = $this->subscriberRepository->findOneByUniqueId($uid);
        $message = $this->messageRepository->findById($messageId);

        if ($subscriber === null || $message === null) {
            return;
        }

        $userMessage = $this->userMessageRepository->findByUserAndMessage($subscriber, $message);
        if ($userMessage === null) {
            return;
        }

        $userMessage->setViewedNow();
        $message->getMetadata()->incrementViews();

        $data = [];
        foreach (['HTTP_USER_AGENT', 'HTTP_REFERER'] as $key) {
            if (isset($metadata[$key])) {
                $data[$key] = htmlspecialchars(strip_tags($metadata[$key]));
            }
        }

        $userMessageView = new UserMessageView();
        $userMessageView->setUserId($subscriber->getId());
        $userMessageView->setMessageId($messageId);
        $userMessageView->setViewedNow();
        $userMessageView->setIp($metadata['client_ip'] ?? null);
        $userMessageView->setData(serialize($data));

        $this->entityManager->persist($userMessageView);
    }
}
