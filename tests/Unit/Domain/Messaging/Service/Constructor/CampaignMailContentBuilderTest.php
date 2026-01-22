<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Constructor;

use PhpList\Core\Domain\Common\Html2Text;
use PhpList\Core\Domain\Common\RemotePageFetcher;
use PhpList\Core\Domain\Common\TextParser;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Service\MessagePlaceholderProcessor;
use PhpList\Core\Domain\Messaging\Exception\RemotePageFetchException;
use PhpList\Core\Domain\Messaging\Exception\SubscriberNotFoundException;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Messaging\Service\Constructor\CampaignMailContentBuilder;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CampaignMailContentBuilderTest extends TestCase
{
    private SubscriberRepository&MockObject $subscriberRepository;
    private RemotePageFetcher&MockObject $remotePageFetcher;
    private EventLogManager&MockObject $eventLogManager;
    private ConfigProvider&MockObject $configProvider;
    private Html2Text&MockObject $html2Text;
    private TextParser&MockObject $textParser;
    private MessagePlaceholderProcessor&MockObject $placeholderProcessor;

    protected function setUp(): void
    {
        $this->subscriberRepository = $this->createMock(SubscriberRepository::class);
        $this->remotePageFetcher = $this->getMockBuilder(RemotePageFetcher::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__invoke'])
            ->getMock();
        $this->eventLogManager = $this->createMock(EventLogManager::class);
        $this->configProvider = $this->createMock(ConfigProvider::class);
        $this->html2Text = $this->getMockBuilder(Html2Text::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__invoke'])
            ->getMock();
        $this->textParser = $this->getMockBuilder(TextParser::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__invoke'])
            ->getMock();
        $this->placeholderProcessor = $this->createMock(MessagePlaceholderProcessor::class);

        $this->configProvider
            ->method('getValue')
            ->willReturnMap(
                [
                [ConfigOption::HtmlEmailStyle, '<style>/*default-style*/</style>'],
                ]
            );
    }

    private function makeBuilder(): CampaignMailContentBuilder
    {
        return new CampaignMailContentBuilder(
            subscriberRepository: $this->subscriberRepository,
            remotePageFetcher: $this->remotePageFetcher,
            eventLogManager: $this->eventLogManager,
            configProvider: $this->configProvider,
            html2Text: $this->html2Text,
            textParser: $this->textParser,
            placeholderProcessor: $this->placeholderProcessor,
        );
    }

    public function testThrowsWhenSubscriberNotFound(): void
    {
        $dto = new MessagePrecacheDto();
        $dto->to = 'missing@example.com';
        $dto->content = 'Hello';

        $this->subscriberRepository->method('findOneByEmail')->willReturn(null);

        $builder = $this->makeBuilder();
        $this->expectException(SubscriberNotFoundException::class);
        $builder($dto, 10);
    }

    public function testBuildsHtmlFormattedGeneratesTextViaHtml2Text(): void
    {
        $subscriber = $this->getMockBuilder(Subscriber::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getEmail'])
            ->getMock();
        $subscriber->method('getId')->willReturn(123);
        $subscriber->method('getEmail')->willReturn('user@example.com');

        $this->subscriberRepository
            ->method('findOneByEmail')
            ->willReturn($subscriber);
        $this->placeholderProcessor
            ->method('process')
            ->willReturnCallback(
                static function (...$args): string {
                    return (string) $args[0];
                }
            );

        $dto = new MessagePrecacheDto();
        $dto->to = 'user@example.com';
        $dto->content = '<b>Hi</b>';
        $dto->htmlFormatted = true;

        $this->html2Text->expects($this->once())
            ->method('__invoke')
            ->with('<b>Hi</b>')
            ->willReturn('Hi');

        $builder = $this->makeBuilder();
        [$html, $text] = $builder($dto, 5);

        $this->assertSame('Hi', $text);
        $this->assertStringContainsString('<b>Hi</b>', $html);
        $this->assertStringContainsString('<html', $html);
        $this->assertStringContainsString('<head>', $html);
        $this->assertStringContainsString(
            '/*default-style*/',
            $html,
            'Default style should be added when no template is used'
        );
    }

    public function testBuildsFromPlainTextUsingTextParser(): void
    {
        $subscriber = $this->getMockBuilder(Subscriber::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getEmail'])
            ->getMock();
        $subscriber->method('getId')->willReturn(22);
        $subscriber->method('getEmail')->willReturn('user@example.com');
        $this->subscriberRepository
            ->method('findOneByEmail')
            ->willReturn($subscriber);
        $this->placeholderProcessor
            ->method('process')
            ->willReturnCallback(
                static function (...$args): string {
                    return (string) $args[0];
                }
            );

        $dto = new MessagePrecacheDto();
        $dto->to = 'user@example.com';
        $dto->content = 'Hello world';
        $dto->htmlFormatted = false;

        $this->textParser->expects($this->once())
            ->method('__invoke')
            ->with('Hello world')
            ->willReturn('<p>Hello world</p>');

        $builder = $this->makeBuilder();
        [$html, $text] = $builder($dto, 7);

        $this->assertSame('Hello world', $text);
        $this->assertStringContainsString('<p>Hello world</p>', $html);
        $this->assertStringContainsString('/*default-style*/', $html);
    }

    public function testUserSpecificUrlReplacementAndExceptionOnEmpty(): void
    {
        $subscriber = $this->getMockBuilder(Subscriber::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getEmail'])
            ->getMock();
        $subscriber->method('getId')->willReturn(55);
        $subscriber->method('getEmail')->willReturn('user@example.com');
        $this->subscriberRepository
            ->method('findOneByEmail')
            ->willReturn($subscriber);
        $this->subscriberRepository
            ->method('getDataById')
            ->with(55)
            ->willReturn(['id' => 55]);

        // Success path replacement
        $dto = new MessagePrecacheDto();
        $dto->to = 'user@example.com';
        $dto->content = 'Intro [URL:example.com/path] End';
        $dto->userSpecificUrl = true;

        $this->remotePageFetcher
            ->expects($this->exactly(2))
            ->method('__invoke')
            ->withConsecutive(
                ['https://example.com/path', ['id' => 55]],
                ['https://example.com/empty', ['id' => 55]],
            )
            ->willReturnOnConsecutiveCalls('<div>REMOTE</div>', '');
        $this->placeholderProcessor
            ->method('process')
            ->willReturnCallback(
                static function (...$args): string {
                    return (string) $args[0];
                }
            );

        $builder = $this->makeBuilder();
        [$html] = $builder($dto, 11);
        $this->assertStringContainsString('<!--https://example.com/path--><div>REMOTE</div>', $html);

        // Failure path (empty content) should log and throw
        $dto2 = new MessagePrecacheDto();
        $dto2->to = 'user@example.com';
        $dto2->content = 'Again [URL:example.com/empty] test';
        $dto2->userSpecificUrl = true;

        $this->eventLogManager
            ->expects($this->once())
            ->method('log');

        $this->expectException(RemotePageFetchException::class);
        $builder($dto2, 12);
    }

    public function testTemplatePreventsDefaultStyleInjection(): void
    {
        $subscriber = $this->getMockBuilder(Subscriber::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getEmail'])
            ->getMock();
        $subscriber->method('getId')->willReturn(77);
        $subscriber->method('getEmail')->willReturn('user@example.com');
        $this->subscriberRepository
            ->method('findOneByEmail')
            ->willReturn($subscriber);

        $this->placeholderProcessor
            ->method('process')
            ->willReturnCallback(
                static function (...$args): string {
                    return (string) $args[0];
                }
            );

        $dto = new MessagePrecacheDto();
        $dto->to = 'user@example.com';
        $dto->content = '<p>Inner</p>';
        $dto->htmlFormatted = true;
        $dto->template = '<html><head><title>T</title></head><body>BEFORE[CONTENT]AFTER</body></html>';

        $builder = $this->makeBuilder();
        [$html, $text] = $builder($dto, 2);

        $this->assertStringContainsString('BEFORE<p>Inner</p>AFTER', $html);
        $this->assertStringNotContainsString(
            '/*default-style*/',
            $html,
            'Default style must not be added when template provided'
        );
        $this->assertSame(
            '',
            $text,
            'No text content provided and html2text not used when htmlFormatted and template present'
        );
    }
}
