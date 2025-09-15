<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class RateLimitedCampaignMailer
{
    private MailerInterface $mailer;
    private SendRateLimiter $limiter;
    public function __construct(MailerInterface $mailer, SendRateLimiter $limiter)
    {
        $this->mailer = $mailer;
        $this->limiter = $limiter;
    }

    public function composeEmail(Message $processed, Subscriber $subscriber): Email
    {
        return (new Email())
            ->from('news@example.com')
            ->to($subscriber->getEmail())
            ->subject($processed->getContent()->getSubject())
            ->text($processed->getContent()->getTextMessage())
            ->html($processed->getContent()->getText());
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
