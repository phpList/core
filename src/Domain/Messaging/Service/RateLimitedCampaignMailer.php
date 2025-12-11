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

    public function composeEmail(
        Message $message,
        Subscriber $subscriber,
        Message\MessageContent $processedContent,
    ): Email {
        $email = new Email();
        if ($message->getOptions()->getFromField() !== '') {
            $email->from($message->getOptions()->getFromField());
        }

        if ($message->getOptions()->getReplyTo() !== '') {
            $email->replyTo($message->getOptions()->getReplyTo());
        }

        return $email
            ->to($subscriber->getEmail())
            ->subject($processedContent->getSubject())
            // todo: check HTML2Text functionality
            ->text($processedContent->getTextMessage())
            ->html($processedContent->getText());
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
