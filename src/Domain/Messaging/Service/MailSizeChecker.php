<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Messaging\Exception\MessageSizeLimitExceededException;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Service\Manager\MessageDataManager;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Mime\Email;

class MailSizeChecker
{
    private ?int $maxMailSize;

    public function __construct(
        private readonly EventLogManager $eventLogManager,
        private readonly MessageDataManager $messageDataManager,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        ?int $maxMailSize = null,
    ) {
        $this->maxMailSize = $maxMailSize ?? 0;
    }

    public function __invoke(Message $campaign, Email $email, bool $hasHtmlEmail): void
    {
        if ($this->maxMailSize <= 0) {
            return;
        }
        $sizeName = $hasHtmlEmail ? 'htmlsize' : 'textsize';
        $cacheKey = sprintf('messaging.size.%d.%s', $campaign->getId(), $sizeName);
        if (!$this->cache->has($cacheKey)) {
            $size = $this->calculateEmailSize($email);
            $this->messageDataManager->setMessageData($campaign, $sizeName, $size);
            $this->cache->set($cacheKey, $size);
        }

        $size = $this->cache->get($cacheKey);
        if ($size <= $this->maxMailSize) {
            return;
        }

        $this->logger->warning(sprintf(
            'Message too large (%d is over %d), suspending campaign %d',
            $size,
            $this->maxMailSize,
            $campaign->getId()
        ));

        $this->eventLogManager->log('send', sprintf(
            'Message too large (%d is over %d), suspending',
            $size,
            $this->maxMailSize
        ));

        $this->eventLogManager->log('send', sprintf(
            'Campaign %d suspended. Message too large',
            $campaign->getId()
        ));

        throw new MessageSizeLimitExceededException($size, $this->maxMailSize);
    }

    private function calculateEmailSize(Email $email): int
    {
        $size = 0;

        foreach ($email->toIterable() as $line) {
            $size += strlen($line);
        }

        return $size;
    }
}
