<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Configuration\Model\OutputFormat;
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
        private readonly MessageProcessingPreparator $messagePreparator,
        private readonly ForwardEmailBuilder $forwardEmailBuilder,
    ) {
    }

    /** @return array{Email, OutputFormat}|null */
    public function getContents(
        Message $campaign,
        Subscriber $forwardingSubscriber,
        string $friendEmail,
        MessageForwardDto $forwardDto
    ): ?array {
        $messagePrecacheDto = $this->cache->get(sprintf('messaging.message.base.%d.%d', $campaign->getId(), 1));

        // todo: check how should links be handled in case of forwarding
        $processed = $this->messagePreparator->processMessageLinks(
            campaignId: $campaign->getId(),
            cachedMessageDto: $messagePrecacheDto,
            subscriber: $forwardingSubscriber
        );

        return $this->forwardEmailBuilder->buildForwardEmail(
            messageId: $campaign->getId(),
            email: $friendEmail,
            forwardedBy: $forwardingSubscriber,
            data: $processed,
            htmlPref: $forwardingSubscriber->hasHtmlEmail(),
            fromName: $forwardDto->getFromName(),
            fromEmail: $forwardDto->getFromEmail(),
            forwardedPersonalNote: $forwardDto->getNote()
        );
    }
}
