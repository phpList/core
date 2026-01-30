<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Service;

use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminNotifier
{
    public function __construct(
        private readonly AdminCopyEmailSender $adminCopyEmailSender,
        private readonly TranslatorInterface $translator,
        private readonly EventLogManager $eventLogManager,
    ) {
    }

    public function notifyForwardFailed(
        Message $campaign,
        Subscriber $forwardingSubscriber,
        string $friendEmail,
        array $lists
    ): void {
        ($this->adminCopyEmailSender)(
            subject: $this->translator->trans('Message Forwarded'),
            message: $this->translator->trans(
                '%subscriber% tried forwarding message %campaignId% to %email% but failed',
                [
                    '%subscriber%' => $forwardingSubscriber->getEmail(),
                    '%campaignId%' => $campaign->getId(),
                    '%email%' => $friendEmail,
                ]
            ),
            lists: $lists
        );

        $this->eventLogManager->log('forward', 'Error loading message ' . $campaign->getId().' in cache');
    }

    public function notifyForwardSucceeded(
        Message $campaign,
        Subscriber $forwardingSubscriber,
        string $friendEmail,
        array $lists
    ): void {
        ($this->adminCopyEmailSender)(
            subject: $this->translator->trans('Message Forwarded'),
            message: $this->translator->trans(
                '%subscriber% has forwarded message %campaignId% to %email%',
                [
                    '%subscriber%' => $forwardingSubscriber->getEmail(),
                    '%campaignId%' => $campaign->getId(),
                    '%email%' => $friendEmail,
                ]
            ),
            lists: $lists
        );
    }
}
