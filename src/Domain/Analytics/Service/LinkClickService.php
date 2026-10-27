<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Service;

use DateTimeImmutable;
use PhpList\Core\Domain\Analytics\Model\LinkTrackForward;
use PhpList\Core\Domain\Analytics\Repository\LinkTrackForwardRepository;
use PhpList\Core\Domain\Analytics\Repository\LinkTrackMlRepository;
use PhpList\Core\Domain\Analytics\Repository\LinkTrackUmlClickRepository;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;

/**
 * Handles a click on a tracked link, like phplist3's lt.php: validates the token, counts the click and returns
 * the url to redirect to.
 */
class LinkClickService
{
    public function __construct(
        private readonly LinkTrackTokenService $tokenService,
        private readonly LinkTrackForwardRepository $forwardRepository,
        private readonly LinkTrackMlRepository $mlRepository,
        private readonly LinkTrackUmlClickRepository $umlClickRepository,
        private readonly MessageRepository $messageRepository,
        private readonly SubscriberRepository $subscriberRepository,
        private readonly UserMessageRepository $userMessageRepository,
        private readonly UserMessageService $userMessageService,
    ) {
    }

    /**
     * The caller has to flush the entity manager, as the first click of a campaign also counts as a view.
     *
     * @param array<string, mixed> $metadata HTTP_USER_AGENT, HTTP_REFERER and client_ip of the request
     * @return string|null the url to redirect to, or null when the token is invalid or the link is unknown
     */
    public function trackClick(string $tid, ?string $signature, array $metadata): ?string
    {
        $token = $this->tokenService->parse($tid, $signature);
        if ($token === null) {
            return null;
        }

        $forward = $this->forwardRepository->findOneByUuid($token->forwardUuid);
        $message = $this->messageRepository->findOneBy(['uuid' => $token->messageUuid]);
        $subscriber = $this->subscriberRepository->findOneBy(['uuid' => $token->subscriberUuid]);
        if (!$forward instanceof LinkTrackForward
            || !$message instanceof Message
            || !$subscriber instanceof Subscriber
        ) {
            return null;
        }

        $userMessage = $this->userMessageRepository->findByUserAndMessage($subscriber, $message);
        $url = $this->resolveUrl($forward, $subscriber, $userMessage);
        if ($url === null) {
            return null;
        }

        $now = new DateTimeImmutable();
        $messageId = (int) $message->getId();
        $forwardId = (int) $forward->getId();
        $this->mlRepository->recordClick($messageId, $forwardId, $token->isHtml(), $now);

        // a click on a link also proves the campaign was opened, but only the first one counts as a view
        if ($userMessage !== null && !$userMessage->isViewed()) {
            $this->userMessageService->trackUserMessageView($subscriber->getUniqueId(), $messageId, $metadata);
        }

        $this->umlClickRepository->recordClick(
            $messageId,
            (int) $subscriber->getId(),
            $forwardId,
            $token->isHtml(),
            $now
        );

        return $url;
    }

    private function resolveUrl(LinkTrackForward $forward, Subscriber $subscriber, ?UserMessage $userMessage): ?string
    {
        $url = $forward->getUrl();
        if ($url === null) {
            return null;
        }

        if ($forward->isPersonalise()) {
            // personalised links are only valid for subscribers the campaign was actually sent to
            if ($userMessage === null) {
                return null;
            }
            // the uid has to go before any fragment, which is never sent to the server
            $fragmentPos = strpos($url, '#');
            $fragment = $fragmentPos === false ? '' : substr($url, $fragmentPos);
            $base = $fragmentPos === false ? $url : substr($url, 0, $fragmentPos);
            $url = $base . (str_contains($base, '?') ? '&' : '?') . 'uid=' . $subscriber->getUniqueId() . $fragment;
        }

        return preg_match('~^(https?|ftp)://~i', $url) ? $url : null;
    }
}
