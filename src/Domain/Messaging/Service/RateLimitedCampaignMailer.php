<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Common\RemotePageFetcher;
use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Messaging\Exception\RemotePageFetchException;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class RateLimitedCampaignMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly SendRateLimiter $limiter,
        private readonly RemotePageFetcher $remotePageFetcher,
        private readonly SubscriberRepository $subscriberRepository,
        private readonly EventLogManager $eventLogManager,
    ) {
    }

    public function composeEmail(
        Message $message,
        Subscriber $subscriber,
        MessagePrecacheDto $messagePrecacheDto,
    ): Email {
        $email = new Email();
        if ($messagePrecacheDto->userSpecificUrl) {
            $userData = $this->subscriberRepository->getDataById($subscriber->getId());
            $this->replaceUserSpecificRemoteContent($messagePrecacheDto, $subscriber, $userData);
        }

        if ($message->getOptions()->getFromField() !== '') {
            $email->from($message->getOptions()->getFromField());
        }

        if ($message->getOptions()->getReplyTo() !== '') {
            $email->replyTo($message->getOptions()->getReplyTo());
        }

        $html = $messagePrecacheDto->content . $messagePrecacheDto->htmlFooter;

        return $email
            ->to($subscriber->getEmail())
            ->subject($messagePrecacheDto->subject)
            ->text($messagePrecacheDto->textContent)
            ->html($html);
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

    private function replaceUserSpecificRemoteContent(
        MessagePrecacheDto $messagePrecacheDto,
        Subscriber $subscriber,
        array $userData
    ): void {
        if (!preg_match_all('/\[URL:([^\s]+)\]/i', $messagePrecacheDto->content, $matches, PREG_SET_ORDER)) {
            return;
        }

        $content = $messagePrecacheDto->content;
        foreach ($matches as $match) {
            $token = $match[0];
            $rawUrl = $match[1];

            if (!$rawUrl) {
                continue;
            }

            $url = preg_match('/^https?:\/\//i', $rawUrl) ? $rawUrl : 'http://' . $rawUrl;

            $remoteContent = ($this->remotePageFetcher)($url, $userData);

            if ($remoteContent === null) {
                $this->eventLogManager->log(
                    '',
                    sprintf('Error fetching URL: %s to send to %s', $rawUrl, $subscriber->getEmail())
                );

                throw new RemotePageFetchException();
            }

            $content = str_replace($token, '<!--' . $url . '-->' . $remoteContent, $content);
        }

        $messagePrecacheDto->content = $content;
        $messagePrecacheDto->htmlFormatted = strip_tags($content) !== $content;
    }
}
