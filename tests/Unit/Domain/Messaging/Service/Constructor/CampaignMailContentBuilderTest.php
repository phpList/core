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
        $dto->content = '<b>Hi</b>';
        $dto->htmlFormatted = true;

        $this->html2Text->expects($this->once())
            ->method('__invoke')
            ->with('<b>Hi</b>')
            ->willReturn('Hi');

        $builder = $this->makeBuilder();
        [$html, $text] = $builder($dto, $subscriber, 5);

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
        $dto->content = 'Hello world';
        $dto->htmlFormatted = false;

        $this->textParser->expects($this->once())
            ->method('__invoke')
            ->with('Hello world')
            ->willReturn('<p>Hello world</p>');

        $builder = $this->makeBuilder();
        [$html, $text] = $builder($dto, $subscriber, 7);

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

        $remotePageFetcherCalls = [];

        $this->remotePageFetcher
            ->expects($this->exactly(2))
            ->method('__invoke')
            ->willReturnCallback(
                function (string $url, array $data) use (&$remotePageFetcherCalls): string {
                    $remotePageFetcherCalls[] = [$url, $data];

                    return match ($url) {
                        'https://example.com/path' => '<div>REMOTE</div>',
                        'https://example.com/empty' => '',
                        default => '<!--' . $url . '--><div>UNKNOWN</div>'
                    };
                }
            );

        $this->placeholderProcessor
            ->method('process')
            ->willReturnCallback(
                static function (...$args): string {
                    return (string) $args[0];
                }
            );

        $builder = $this->makeBuilder();

        $dto = new MessagePrecacheDto();
        $dto->content = 'Intro [URL:example.com/path] End';
        $dto->userSpecificUrl = true;

        [$html] = $builder($dto, $subscriber, 11);

        $this->assertSame(
            [
                ['https://example.com/path', ['id' => 55]],
            ],
            [$remotePageFetcherCalls[0]],
        );

        $this->assertStringContainsString(
            '<!--https://example.com/path--><div>REMOTE</div>',
            $html
        );

        $dto2 = new MessagePrecacheDto();
        $dto2->content = 'Again [URL:example.com/empty] test';
        $dto2->userSpecificUrl = true;

        $this->eventLogManager
            ->expects($this->once())
            ->method('log');

        $this->expectException(RemotePageFetchException::class);

        try {
            $builder($dto2, $subscriber, 12);
        } finally {
            $this->assertSame(
                [
                    ['https://example.com/path', ['id' => 55]],
                    ['https://example.com/empty', ['id' => 55]],
                ],
                $remotePageFetcherCalls,
            );
        }
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
        $dto->content = '<p>Inner</p>';
        $dto->htmlFormatted = true;
        $dto->template = '<html><head><title>T</title></head><body>BEFORE[CONTENT]AFTER</body></html>';

        $builder = $this->makeBuilder();
        [$html, $text] = $builder($dto, $subscriber, 2);

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
