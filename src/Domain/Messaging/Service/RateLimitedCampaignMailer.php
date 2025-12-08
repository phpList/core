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

    public function composeEmail(Message $processed, Subscriber $subscriber, Message\MessageContent $content): Email
    {
        $email = new Email();
        if ($processed->getOptions()->getFromField() !== '') {
            $email->from($processed->getOptions()->getFromField());
        }

        if ($processed->getOptions()->getReplyTo() !== '') {
            $email->replyTo($processed->getOptions()->getReplyTo());
        }

        return $email
            ->to($subscriber->getEmail())
            ->subject($content->getSubject())
            // todo: check HTML2Text functionality
            ->text($content->getTextMessage())
            ->html($content->getText());
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
