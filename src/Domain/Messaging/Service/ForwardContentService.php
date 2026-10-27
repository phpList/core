<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Messaging\Exception\EmailBlacklistedException;
use PhpList\Core\Domain\Messaging\Exception\InvalidRecipientOrSubjectException;
use PhpList\Core\Domain\Messaging\Exception\MessageCacheMissingException;
use PhpList\Core\Domain\Messaging\Model\Dto\MessageForwardDto;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Service\Builder\ForwardEmailBuilder;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Mime\Email;

class ForwardContentService
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly ForwardEmailBuilder $forwardEmailBuilder,
    ) {
    }

    /** @return array{Email, OutputFormat}
     * @throws MessageCacheMissingException | InvalidRecipientOrSubjectException | EmailBlacklistedException
     */
    public function getContents(
        Message $campaign,
        Subscriber $forwardingSubscriber,
        string $friendEmail,
        MessageForwardDto $forwardDto
    ): array {
        $messagePrecacheDto = $this->cache->get(sprintf('messaging.message.base.%d.%d.%d', $campaign->getId(), 1, 0));

        if ($messagePrecacheDto === null) {
            throw new MessageCacheMissingException();
        }

        // links of forwarded messages are not click tracked: the forwarding subscriber would
        // be credited with the clicks of the friend
        return $this->forwardEmailBuilder->buildForwardEmail(
            messageId: $campaign->getId(),
            friendEmail: $friendEmail,
            forwardedBy: $forwardingSubscriber,
            data: $messagePrecacheDto,
            htmlPref: $forwardingSubscriber->hasHtmlEmail(),
            fromName: $forwardDto->getFromName(),
            fromEmail: $forwardDto->getFromEmail(),
            forwardedPersonalNote: $forwardDto->getNote()
        );
    }
}
