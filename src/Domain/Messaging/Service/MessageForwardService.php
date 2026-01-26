<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use DateTimeInterface;
use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Repository\UserMessageForwardRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
use PhpList\Core\Domain\Messaging\Service\Builder\ForwardEmailBuilder;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeValueRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberListRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

class MessageForwardService
{
    public function __construct(
        private readonly UserMessageForwardRepository $forwardRepository,
        private readonly SubscriberRepository $subscriberRepository,
        private readonly MessageDataLoader $messageDataLoader,
        private readonly TranslatorInterface $translator,
        private readonly UserMessageRepository $userMessageRepository,
        private readonly SubscriberAttributeValueRepository $subscriberAttributeValueRepo,
        private readonly SubscriberListRepository $subscriberListRepository,
        private readonly CacheInterface $cache,
        private readonly MessageProcessingPreparator $messagePreparator,
        private readonly ForwardEmailBuilder $forwardEmailBuilder,
        private readonly MessagePrecacheService $precacheService,
        private readonly EventLogManager $eventLogManager,
        #[Autowire('%phplist.forward_friend_count_attribute%')] private readonly string $forwardFriendCountAttribute,
    ) {
    }

    public function forward(array $emails, string $uid, Message $campaign, DateTimeInterface $cutoff): void
    {
        $loadedMessageData = ($this->messageDataLoader)($campaign);
        $subtitle = $this->translator->trans('Forwarding the message with subject') . ' ' . stripslashes($loadedMessageData['subject']);
        $subscriber = $this->subscriberRepository->findOneByUniqueId($uid);
        $receivedMessage = $this->userMessageRepository->findOneByUserAndMessage($subscriber, $campaign);
        if ($receivedMessage === null) {
            // todo: do something
        }

        if ($this->forwardFriendCountAttribute && $this->forwardFriendCountAttribute !== '') {
            $iCountFriends = $this->forwardFriendCountAttribute;
        } else {
            $iCountFriends = 0;
        }
        if ($iCountFriends) {
            $nFriends = $this->subscriberAttributeValueRepo
                ->findOneBySubscriberAndAttributeName($subscriber, $this->forwardFriendCountAttribute)
                ?->getValue();
        }

        $messagelists = $this->subscriberListRepository->getListsByMessage($campaign);
        if (!$this->precacheService->precacheMessage($campaign, $loadedMessageData, true)) {
//            sendAdminCopy(s('Message Forwarded'),
//                s('%s tried forwarding message %d to %s but failed', $userdata['email'], $mid, $email),
//                $messagelists);
//            Sql_Query(sprintf('insert into %s (user,message,forward,status)
//                values(%d,%d,"%s","failed")',
//                $tables['user_message_forward'], $userdata['id'], $mid, $email));
            $ok = false;
            $this->eventLogManager->log('forward', 'Error loading message '.$campaign->getId().'  in cache');
        }
        $messagePrecacheDto = $this->cache->get(sprintf('messaging.message.base.%d.%d', $campaign->getId(), 1));

        $processed = $this->messagePreparator->processMessageLinks(
            campaignId: $campaign->getId(),
            cachedMessageDto: $messagePrecacheDto,
            subscriber: $subscriber
        );

        foreach ($emails as $email) {
            $done = $this->forwardRepository->findByEmailAndMessage($email, $campaign->getId());
            if ($done === null) {
                $this->forwardEmailBuilder->buildForwardEmail(
                    messageId: $campaign->getId(),
                    email: $email,
                    forwardedBy: $subscriber,
                    data: $processed,
                    htmlPref: $subscriber->hasHtmlEmail(),
                );
            }
        }

        $forwardPeriodCount = $this->forwardRepository->getCountByUserSince($subscriber, $cutoff);

    }
}
