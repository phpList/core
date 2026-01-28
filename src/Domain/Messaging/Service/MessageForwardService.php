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
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
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
        private readonly MailerInterface $mailer,
        private readonly AdminCopyEmailSender $adminCopyEmailSender,
        #[Autowire('%phplist.forward_friend_count_attribute%')] private readonly string $forwardFriendCountAttribute,
        #[Autowire('%imap_bounce.email%')] private readonly string $bounceEmail,
    ) {
    }

    public function forward(array $emails, string $uid, Message $campaign, DateTimeInterface $cutoff, ?string $note = null, string $fromName, string $fromEmail): void
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

        $messageLists = $this->subscriberListRepository->getListsByMessage($campaign);

        foreach ($emails as $friendEmail) {
            $done = $this->forwardRepository->findByEmailAndMessage($friendEmail, $campaign->getId());
            if ($done === null) {
                if (!$this->precacheService->precacheMessage($campaign, $loadedMessageData, true)) {
                    ($this->adminCopyEmailSender)(
                        $this->translator->trans('Message Forwarded'),
                        $this->translator->trans(
                            '%subscriber% tried forwarding message %campaignId% to %email% but failed',
                            [
                                '%subscriber%' => $subscriber->getEmail(),
                                '%campaignId%' => $campaign->getId(),
                                '%email%' => $friendEmail,
                            ]
                        ),
                        $messageLists
                    );

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
                [$email, $sentAs] = $this->forwardEmailBuilder->buildForwardEmail(
                    messageId: $campaign->getId(),
                    email: $friendEmail,
                    forwardedBy: $subscriber,
                    data: $processed,
                    htmlPref: $subscriber->hasHtmlEmail(),
                    fromName: $fromName,
                    fromEmail: $fromEmail,
                    forwardedPersonalNote: $note
                );

                $envelope = new Envelope(
                    sender: new Address($this->bounceEmail, 'PHPList'),
                    recipients: [new Address($email->getAddress())],
                );
                $this->mailer->send(message: $friendEmail, envelope: $envelope);
                $campaign->incrementSentCount($sentAs);
            }
        }

        $forwardPeriodCount = $this->forwardRepository->getCountByUserSince($subscriber, $cutoff);

    }
}
