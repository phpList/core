<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service;

use DateTime;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Messaging\Exception\MessageCacheMissingException;
use PhpList\Core\Domain\Messaging\Model\Dto\MessageForwardDto;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Service\ForwardContentService;
use PhpList\Core\Domain\Messaging\Service\MessageProcessingPreparator;
use PhpList\Core\Domain\Messaging\Service\Builder\ForwardEmailBuilder;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Mime\Email;

class ForwardContentServiceTest extends TestCase
{
    private CacheInterface&MockObject $cache;
    private MessageProcessingPreparator&MockObject $preparator;
    private ForwardEmailBuilder&MockObject $builder;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->preparator = $this->createMock(MessageProcessingPreparator::class);
        $this->builder = $this->createMock(ForwardEmailBuilder::class);
    }

    public function testThrowsWhenCacheMissing(): void
    {
        $service = new ForwardContentService(
            cache: $this->cache,
            messagePreparator: $this->preparator,
            forwardEmailBuilder: $this->builder,
        );

        $campaign = $this->createMock(Message::class);
        $campaign->method('getId')->willReturn(10);
        $subscriber = new Subscriber('alice@example.com');

        $this->cache
            ->expects(self::once())
            ->method('get')
            ->with('messaging.message.base.10.1')
            ->willReturn(null);

        $this->expectException(MessageCacheMissingException::class);

        $service->getContents(
            campaign: $campaign,
            forwardingSubscriber: $subscriber,
            friendEmail: 'friend@example.com',
            forwardDto: new MessageForwardDto(
                [],
                'uuid',
                'from@example.com',
                'From',
                null
            )
        );
    }

    public function testProcessesLinksAndDelegatesToBuilder(): void
    {
        $service = new ForwardContentService(
            cache: $this->cache,
            messagePreparator: $this->preparator,
            forwardEmailBuilder: $this->builder,
        );

        $campaign = $this->createMock(Message::class);
        $campaign->method('getId')->willReturn(42);
        $subscriber = new Subscriber('alice@example.com');
        $subscriber->setHtmlEmail(true);

        $cached = new MessagePrecacheDto();
        $processed = new MessagePrecacheDto();

        $this->cache
            ->expects(self::once())
            ->method('get')
            ->with('messaging.message.base.42.1')
            ->willReturn($cached);

        $this->preparator
            ->expects(self::once())
            ->method('processMessageLinks')
            ->with(
                42,
                $cached,
                $subscriber
            )
            ->willReturn($processed);

        $expectedEmail = new Email();
        $this->builder
            ->expects(self::once())
            ->method('buildForwardEmail')
            ->with(
                42,
                'f@example.com',
                $subscriber,
                $processed,
                true,
                'From Name',
                'from@example.com',
                'note'
            )
            ->willReturn([$expectedEmail, OutputFormat::Text]);

        $result = $service->getContents(
            campaign: $campaign,
            forwardingSubscriber: $subscriber,
            friendEmail: 'f@example.com',
            forwardDto: new MessageForwardDto(
                ['f@example.com'],
                'uuid',
                'From Name',
                'from@example.com',
                'note'
            )
        );

        self::assertSame($expectedEmail, $result[0]);
        self::assertSame(OutputFormat::Text, $result[1]);
    }
}
