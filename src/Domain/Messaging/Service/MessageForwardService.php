<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use DateTimeInterface;
use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Messaging\Exception\ForwardLimitExceededException;
use PhpList\Core\Domain\Messaging\Exception\MessageNotReceivedException;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Repository\UserMessageForwardRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
use PhpList\Core\Domain\Messaging\Service\Builder\ForwardEmailBuilder;
use PhpList\Core\Domain\Messaging\Service\Manager\UserMessageForwardManager;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeValueRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberListRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberAttributeManager;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

class MessageForwardService
{
    private readonly ?string $forwardFriendCountAttribute;

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
        private readonly MailerInterface $mailer,
        private readonly AdminCopyEmailSender $adminCopyEmailSender,
        private readonly UserMessageForwardManager $messageForwardManager,
        private readonly SubscriberAttributeManager $subscriberAttributeManager,
        #[Autowire('%phplist.forward_friend_count_attribute%')] string $forwardFriendCountAttr,
        #[Autowire('%imap_bounce.email%')] private readonly string $bounceEmail,
        #[Autowire('%phplist.forward_message_count%')] private readonly int $forwardMessageCount,
    ) {
        $forwardFriendCountAttr = trim($forwardFriendCountAttr);
        $this->forwardFriendCountAttribute = $forwardFriendCountAttr !== '' ? $forwardFriendCountAttr : null;
    }

    public function forward(
        array $emails,
        string $uid,
        Message $campaign,
        DateTimeInterface $cutoff,
        string $fromName,
        string $fromEmail,
        ?string $note = null
    ): void {
        $loadedMessageData = ($this->messageDataLoader)($campaign);
        $subscriber = $this->subscriberRepository->findOneByUniqueId($uid);
        $receivedMessage = $this->userMessageRepository->findOneByUserAndMessage($subscriber, $campaign);
        if ($receivedMessage === null) {
            throw new MessageNotReceivedException();
        }

        $forwardPeriodCount = $this->forwardRepository->getCountByUserSince($subscriber, $cutoff);
        if ($forwardPeriodCount > $this->forwardMessageCount) {
            throw new ForwardLimitExceededException();
        }

        if ($this->forwardFriendCountAttribute) {
            $nFriends = $this->subscriberAttributeValueRepo
                ->findOneBySubscriberAndAttributeName($subscriber, $this->forwardFriendCountAttribute)
                ?->getValue();
        }

        $messageLists = $this->subscriberListRepository->getListsByMessage($campaign);

        foreach ($emails as $friendEmail) {
            $existing = $this->forwardRepository->findByEmailAndMessage($friendEmail, $campaign->getId());
            if ($existing !== null && $existing->getStatus() === 'sent') {
                continue;
            }

            if (!$this->precacheService->precacheMessage($campaign, $loadedMessageData, true)) {
                $this->handleFail($campaign, $subscriber, $friendEmail, $messageLists);
                continue;
            }

            $messagePrecacheDto = $this->cache->get(sprintf('messaging.message.base.%d.%d', $campaign->getId(), 1));
            // todo: check how should links be handled in case of forwarding
            $processed = $this->messagePreparator->processMessageLinks(
                campaignId: $campaign->getId(),
                cachedMessageDto: $messagePrecacheDto,
                subscriber: $subscriber
            );

            $result = $this->forwardEmailBuilder->buildForwardEmail(
                messageId: $campaign->getId(),
                email: $friendEmail,
                forwardedBy: $subscriber,
                data: $processed,
                htmlPref: $subscriber->hasHtmlEmail(),
                fromName: $fromName,
                fromEmail: $fromEmail,
                forwardedPersonalNote: $note
            );

            if ($result === null) {
                $this->handleFail($campaign, $subscriber, $friendEmail, $messageLists);
                continue;
            }

            [$email, $sentAs] = $result;
            $envelope = new Envelope(
                sender: new Address($this->bounceEmail, 'PHPList'),
                recipients: [new Address($email->getAddress())],
            );
            $this->mailer->send(message: $friendEmail, envelope: $envelope);
            $this->handleSuccess($campaign, $subscriber, $friendEmail, $messageLists);
            $campaign->incrementSentCount($sentAs);
            if ($this->forwardFriendCountAttribute && isset($nFriends)) {
                ++$nFriends;
            }
        }

        if ($this->forwardFriendCountAttribute && isset($nFriends)) {
            $this->subscriberAttributeManager->createOrUpdateByName(
                subscriber: $subscriber,
                attributeName: $this->forwardFriendCountAttribute,
                value: $nFriends
            );
        }
    }

    private function handleFail($campaign, $subscriber, $friendEmail, $messageLists): void
    {
        ($this->adminCopyEmailSender)(
            subject: $this->translator->trans('Message Forwarded'),
            message: $this->translator->trans(
                '%subscriber% tried forwarding message %campaignId% to %email% but failed',
                [
                    '%subscriber%' => $subscriber->getEmail(),
                    '%campaignId%' => $campaign->getId(),
                    '%email%' => $friendEmail,
                ]
            ),
            lists: $messageLists
        );

        $this->messageForwardManager->create(
            subscriber: $subscriber,
            campaign: $campaign,
            friendEmail: $friendEmail,
            status: 'failed'
        );
        $this->eventLogManager->log('forward', 'Error loading message ' . $campaign->getId().' in cache');
    }

    private function handleSuccess(Message $campaign, ?Subscriber $subscriber, mixed $friendEmail, array $messageLists): void
    {
        ($this->adminCopyEmailSender)(
            subject: $this->translator->trans('Message Forwarded'),
            message: $this->translator->trans(
                '%subscriber% has forwarded message %campaignId% to %email%',
                [
                    '%subscriber%' => $subscriber->getEmail(),
                    '%campaignId%' => $campaign->getId(),
                    '%email%' => $friendEmail,
                ]
            ),
            lists: $messageLists
        );

        $this->messageForwardManager->create(
            subscriber: $subscriber,
            campaign: $campaign,
            friendEmail: $friendEmail,
            status: 'sent'
        );
    }
}
