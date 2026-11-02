<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Analytics\Service;

use PhpList\Core\Domain\Analytics\Service\AnalyticsTrackingService;
use PhpList\Core\Domain\Analytics\Service\LinkRewriter;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Service\MessageDataLoader;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AnalyticsTrackingServiceTest extends TestCase
{
    private ConfigProvider&MockObject $configProvider;
    private MessageDataLoader&MockObject $messageDataLoader;
    private AnalyticsTrackingService $service;

    protected function setUp(): void
    {
        $this->configProvider = $this->createMock(ConfigProvider::class);
        $this->messageDataLoader = $this->createMock(MessageDataLoader::class);
        $this->service = new AnalyticsTrackingService(
            $this->configProvider,
            $this->messageDataLoader,
            new LinkRewriter(),
        );
    }

    private function useTracker(?string $tracker): void
    {
        $this->configProvider->method('getValue')->with(ConfigOption::AnalyticTracker)->willReturn($tracker);
    }

    public function testGoogleParametersAreAddedWithTheEmailFormat(): void
    {
        $this->useTracker('google');

        $url = $this->service->addToUrl(
            'https://example.com/page',
            'HTML',
            ['utm_source' => 'news', 'utm_medium' => 'mail', 'utm_campaign' => 'spring']
        );

        self::assertSame(
            'https://example.com/page?utm_source=news&utm_medium=mail&utm_campaign=spring&utm_content=HTML',
            $url
        );
    }

    public function testMatomoParametersAreUsedWhenConfigured(): void
    {
        $this->useTracker('matomo');

        $url = $this->service->addToUrl(
            'https://example.com/page?a=1',
            'text',
            ['pk_source' => 'news', 'pk_medium' => 'mail', 'pk_campaign' => 'spring', 'utm_source' => 'ignored']
        );

        self::assertSame(
            'https://example.com/page?a=1&pk_source=news&pk_medium=mail&pk_campaign=spring&pk_content=text',
            $url
        );
    }

    public function testCampaignWithoutOwnValuesGetsTheDefaults(): void
    {
        $this->useTracker('google');

        $url = $this->service->addToUrl('https://example.com/', 'HTML', ['subject' => 'Hello World']);

        self::assertSame(
            'https://example.com/?utm_source=phpList&utm_medium=email&utm_campaign=Hello%20World&utm_content=HTML',
            str_replace('+', '%20', $url)
        );
    }

    public function testParameterSetToEmptyIsLeftOutInsteadOfGettingItsDefault(): void
    {
        $this->useTracker('google');

        $url = $this->service->addToUrl(
            'https://example.com/',
            'HTML',
            ['utm_source' => '', 'subject' => 'Hi']
        );

        self::assertSame('https://example.com/?utm_medium=email&utm_campaign=Hi&utm_content=HTML', $url);
    }

    public function testUnknownTrackerFallsBackToGoogle(): void
    {
        $this->useTracker(null);

        self::assertStringContainsString(
            'utm_content=HTML',
            $this->service->addToUrl('https://example.com/', 'HTML', [])
        );
    }

    public function testOnlyHttpUrlsGetParameters(): void
    {
        $this->useTracker('google');

        foreach (['mailto:a@example.com', 'ftp://example.com/f', '[UNSUBSCRIBEURL]', '#top'] as $url) {
            self::assertSame($url, $this->service->addToUrl($url, 'HTML', []));
        }
    }

    public function testExistingTrackingParametersAreReplacedAndTheFragmentStaysLast(): void
    {
        $url = $this->service->withParameters(
            'https://example.com/p?a=1&utm_source=old&utm_medium=old&b=2#section',
            ['utm_source' => 'new', 'utm_content' => 'HTML'],
            'utm_'
        );

        self::assertSame('https://example.com/p?a=1&b=2&utm_source=new&utm_content=HTML#section', $url);
    }

    public function testEmptyParametersAreLeftOut(): void
    {
        self::assertSame(
            'https://example.com/?utm_content=text',
            $this->service->withParameters(
                'https://example.com/',
                ['utm_source' => '', 'utm_medium' => null, 'utm_content' => 'text'],
                'utm_'
            )
        );
        self::assertSame(
            'https://example.com/#x',
            $this->service->withParameters('https://example.com/#x', ['utm_source' => ''], 'utm_')
        );
    }

    public function testBodiesGetParametersOnHtmlLinksAndTextUrls(): void
    {
        $this->useTracker('google');
        $data = ['utm_source' => 'news', 'utm_medium' => 'mail', 'utm_campaign' => 'c'];

        [$html, $text] = $this->service->addToBodies(
            '<a href="https://example.com/a?x=1&amp;y=2">A</a> <a href="mailto:a@example.com">m</a>',
            'Read https://example.com/b. Thanks',
            $data
        );

        self::assertStringContainsString(
            'href="https://example.com/a?x=1&amp;y=2&amp;utm_source=news&amp;utm_medium=mail'
            . '&amp;utm_campaign=c&amp;utm_content=HTML"',
            $html
        );
        self::assertStringContainsString('href="mailto:a@example.com"', $html);
        self::assertSame(
            'Read https://example.com/b?utm_source=news&utm_medium=mail&utm_campaign=c&utm_content=text. Thanks',
            $text
        );
    }

    public function testClickedUrlGetsParametersOnlyWhenTheCampaignTracksAnalytics(): void
    {
        $this->useTracker('google');
        $campaign = $this->createMock(Message::class);
        $this->messageDataLoader->method('__invoke')->with($campaign)->willReturnOnConsecutiveCalls(
            ['google_track' => false, 'utm_source' => 'news'],
            ['google_track' => true, 'utm_source' => 'news', 'utm_medium' => 'm', 'utm_campaign' => 'c'],
        );

        self::assertSame(
            'https://example.com/',
            $this->service->addToClickedUrl('https://example.com/', $campaign, true)
        );
        self::assertSame(
            'https://example.com/?utm_source=news&utm_medium=m&utm_campaign=c&utm_content=text',
            $this->service->addToClickedUrl('https://example.com/', $campaign, false)
        );
    }
}
