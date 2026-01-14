<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class RateLimitedCampaignMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly SendRateLimiter $limiter,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function send(Email $email): void
    {
        $this->limiter->awaitTurn();
        $this->mailer->send($email);
        $this->limiter->afterSend();
    }
}
