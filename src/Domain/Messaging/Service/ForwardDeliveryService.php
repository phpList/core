<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Service\Manager\UserMessageForwardManager;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;

class ForwardDeliveryService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UserMessageForwardManager $messageForwardManager,
        #[Autowire('%imap_bounce.email%')] private readonly string $bounceEmail,
    ) {
    }

    public function send(Email $friendEmail): void
    {
        $envelope = new Envelope(
            sender: new Address($this->bounceEmail, 'PHPList'),
            recipients: [new Address($friendEmail->getTo()[0]->getAddress())],
        );
        $this->mailer->send(message: $friendEmail, envelope: $envelope);
    }

    public function markSent(Message $campaign, Subscriber $subscriber, string $friendEmail): void
    {
        $this->messageForwardManager->create(
            subscriber: $subscriber,
            campaign: $campaign,
            friendEmail: $friendEmail,
            status: 'sent'
        );
    }

    public function markFailed(Message $campaign, Subscriber $subscriber, string $friendEmail): void
    {
        $this->messageForwardManager->create(
            subscriber: $subscriber,
            campaign: $campaign,
            friendEmail: $friendEmail,
            status: 'failed'
        );
    }
}
