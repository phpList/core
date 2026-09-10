<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\MessageData;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CampaignAdminNotifier
{
    public function __construct(
        private readonly SystemNotificationMailer $notificationMailer,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function notifyStart(Message $campaign, array $loadedMessageData, int $messageId): void
    {
        if (empty($loadedMessageData['notify_start']) || isset($loadedMessageData['start_notified'])) {
            return;
        }

        $subject = $this->translator->trans('Campaign started');
        $content = $this->translator->trans(
            'phplist has started sending the campaign with subject %subject%',
            ['%subject%' => $loadedMessageData['subject']]
        );

        foreach (explode(',', $loadedMessageData['notify_start']) as $notification) {
            $this->notificationMailer->send($campaign->getId(), $notification, $subject, $content);
        }

        $messageData = new MessageData();
        $messageData->setName('start_notified');
        $messageData->setId($messageId);
        $messageData->setData((new DateTimeImmutable())->format('Y-m-d H:i:s'));

        try {
            $this->entityManager->persist($messageData);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            $this->logger->debug('Duplicate message ignored', [
                'exception' => $e,
            ]);
        }
    }
}
