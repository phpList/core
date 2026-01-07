<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Constructor;

use PhpList\Core\Domain\Common\RemotePageFetcher;
use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Configuration\Service\UserPersonalizer;
use PhpList\Core\Domain\Messaging\Exception\RemotePageFetchException;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use Symfony\Component\Mime\Email;
// todo: check this class
class PersonalizedContentConstructor
{
    public function __construct(
        private readonly SubscriberRepository $subscriberRepository,
        private readonly RemotePageFetcher $remotePageFetcher,
        private readonly EventLogManager $eventLogManager,
        private readonly UserPersonalizer $userPersonalizer,
    ) {
    }

    public function build(
        Message $message,
        Subscriber $subscriber,
        MessagePrecacheDto $messagePrecacheDto,
    ): Email {
        $content = $messagePrecacheDto->content;

        if ($messagePrecacheDto->userSpecificUrl) {
            $userData = $this->subscriberRepository->getDataById($subscriber->getId());
            $this->replaceUserSpecificRemoteContent($messagePrecacheDto, $subscriber, $userData);
        }



        $mail = new Email();
        return $mail;
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
