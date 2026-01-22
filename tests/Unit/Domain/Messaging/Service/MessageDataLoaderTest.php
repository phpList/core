<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service;

use Doctrine\Common\Collections\ArrayCollection;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\MessageData;
use PhpList\Core\Domain\Messaging\Repository\MessageDataRepository;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Service\MessageDataLoader;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MessageDataLoaderTest extends TestCase
{
    private ConfigProvider&MockObject $config;
    private MessageDataRepository&MockObject $messageDataRepository;
    private MessageRepository&MockObject $messageRepository;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigProvider::class);
        $this->messageDataRepository = $this->createMock(MessageDataRepository::class);
        $this->messageRepository = $this->createMock(MessageRepository::class);
    }

    public function testLoadsMessageDataMergesAndParses(): void
    {
        $defaultMessageAge = 3600;

        $this->config->method('getValue')->willReturnMap([
            [ConfigOption::MessageFromAddress, 'from@example.com'],
            [ConfigOption::AdminAddress, 'admin@example.com'],
            [ConfigOption::DefaultMessageTemplate, '123'],
            [ConfigOption::MessageFooter, 'footer'],
            [ConfigOption::ForwardFooter, 'ffooter'],
            [ConfigOption::NotifyStartDefault, 'start@example.com'],
            [ConfigOption::NotifyEndDefault, 'end@example.com'],
            [ConfigOption::AlwaysAddGoogleTracking, '1'],
        ]);

        $messageId = 10;

        // Non-empty fields from MessageRepository
        $this->messageRepository
            ->method('getNonEmptyFields')
            ->with($messageId)
            ->willReturn([
                'subject' => '(no title)',
                'message' => 'Hello [URL:https://example.org/p]',
                'fromfield' => '',
            ]);

        // Stored message data rows (repository)
        $md1 = (new MessageData())->setId($messageId)->setName('ashtml')->setData('1');
        $md2 = (new MessageData())->setId($messageId)->setName('criteria_match')->setData('any');
        $md3 = (new MessageData())->setId($messageId)->setName('embargo')->setData('string');

        $this->messageDataRepository
            ->method('getForMessage')
            ->with($messageId)
            ->willReturn([$md1, $md2, $md3]);

        // Use a Message mock instead of an anonymous stub
        $message = $this->createMock(Message::class);
        $message->method('getId')->willReturn($messageId);
        $message->method('getListMessages')->willReturn(
            new ArrayCollection([
                new class {
                    public function getListId(): int
                    {
                        return 42;
                    }
                },
            ])
        );

        $loader = new MessageDataLoader(
            configProvider: $this->config,
            messageDataRepository: $this->messageDataRepository,
            messageRepository: $this->messageRepository,
            logger: $this->createMock(LoggerInterface::class),
            defaultMessageAge: $defaultMessageAge
        );

        $before = time();
        $result = ($loader)($message);
        $after = time();

        // Core expectations
        $this->assertSame('123', $result['template']);
        $this->assertTrue($result['google_track']);

        // subject mapping
        $this->assertSame('(no subject)', $result['subject']);

        // stored data merged (and AS_FORMAT_FIELDS ignored)
        $this->assertSame('any', $result['criteria_match']);
        $this->assertArrayNotHasKey('ashtml', $result, 'ashtml should not overwrite values');

        // schedule fields normalized to arrays when not arrays
        $this->assertIsArray($result['embargo']);
        $this->assertIsArray($result['repeatuntil']);
        $this->assertIsArray($result['requeueuntil']);

        // target list from message listMessages
        $this->assertArrayHasKey(42, $result['targetlist']);
        $this->assertSame(1, $result['targetlist'][42]);

        // sendurl inferred from message body
        $this->assertSame('https://example.org/p', $result['sendurl']);
        $this->assertSame('inputhere', $result['sendmethod']);

        // From parsing defaults
        $this->assertSame('from@example.com', $result['fromemail']);
        $this->assertSame('from@example.com', $result['fromname']);

        // finishsending should be now + defaultMessageAge (allow small drift)
        $fs = $result['finishsending'];
        $this->assertIsArray($fs);
        $fsTimestamp = strtotime(sprintf(
            '%s-%s-%s %s:%s:00',
            $fs['year'],
            $fs['month'],
            $fs['day'],
            $fs['hour'],
            $fs['minute']
        ));

        $expectedMin = $before + $defaultMessageAge - 120;
        $expectedMax = $after + $defaultMessageAge + 120;
        $this->assertGreaterThanOrEqual($expectedMin, $fsTimestamp);
        $this->assertLessThanOrEqual($expectedMax, $fsTimestamp);
    }
}
