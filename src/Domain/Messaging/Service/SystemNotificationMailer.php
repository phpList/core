<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Messaging\Service\Builder\SystemEmailBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Builds and sends a one-off system notification email (admin "campaign started" notices,
 * error reports), using the shared bounce address as envelope sender.
 */
class SystemNotificationMailer
{
    public function __construct(
        private readonly SystemEmailBuilder $systemEmailBuilder,
        private readonly MailerInterface $mailer,
        #[Autowire('%imap_bounce.email%')] private readonly string $bounceEmail,
    ) {
    }

    public function send(int $messageId, string $toEmail, string $subject, string $content): bool
    {
        $data = new MessagePrecacheDto();
        $data->subject = $subject;
        $data->content = $content;

        $email = $this->systemEmailBuilder->buildCampaignEmail(
            messageId: $messageId,
            data: $data,
            toEmail: $toEmail,
        );

        if (!$email) {
            return false;
        }

        // todo: check if from name should be from config
        $envelope = new Envelope(
            sender: new Address($this->bounceEmail, 'PHPList'),
            recipients: [new Address($email->getTo()[0]->getAddress())],
        );
        $this->mailer->send(message: $email, envelope: $envelope);

        return true;
    }
}
