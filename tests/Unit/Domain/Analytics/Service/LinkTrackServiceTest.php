<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Analytics\Service;

use PhpList\Core\Core\ParameterProvider;
use PhpList\Core\Domain\Analytics\Model\LinkTrackForward;
use PhpList\Core\Domain\Analytics\Repository\LinkTrackForwardRepository;
use PhpList\Core\Domain\Analytics\Repository\LinkTrackMlRepository;
use PhpList\Core\Domain\Analytics\Repository\LinkTrackRepository;
use PhpList\Core\Domain\Analytics\Service\LinkTrackService;
use PhpList\Core\Domain\Analytics\Service\LinkTrackTokenService;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LinkTrackServiceTest extends TestCase
{
    private LinkTrackForwardRepository&MockObject $forwardRepository;
    private LinkTrackMlRepository&MockObject $mlRepository;
    private LinkTrackTokenService $tokenService;
    private Message&MockObject $campaign;
    private Subscriber $subscriber;
    /** @var array<string, array{url: string, personalise: bool}> */
    private array $createdForwards = [];

    protected function setUp(): void
    {
        $this->forwardRepository = $this->createMock(LinkTrackForwardRepository::class);
        $this->mlRepository = $this->createMock(LinkTrackMlRepository::class);
        $this->tokenService = new LinkTrackTokenService(
            new ParameterProvider([]),
            'https://example.com/api/v2',
            'secret'
        );

        $this->campaign = $this->createMock(Message::class);
        $this->campaign->method('getId')->willReturn(7);
        $this->campaign->method('getUuid')->willReturn('campaign-uuid');

        $this->subscriber = new Subscriber('alice@example.com');
        $this->subscriber->setUuid('subscriber-uuid');

        $this->forwardRepository->method('findOrCreateByUrl')->willReturnCallback(
            function (string $url, bool $personalise): LinkTrackForward {
                $this->createdForwards[$url] = ['url' => $url, 'personalise' => $personalise];
                $id = array_search($url, array_keys($this->createdForwards), true) + 1;

                return $this->createForward($id, $url);
            }
        );
    }

    private function createForward(int $id, string $url): LinkTrackForward&MockObject
    {
        $forward = $this->createMock(LinkTrackForward::class);
        $forward->method('getId')->willReturn($id);
        $forward->method('getUuid')->willReturn('forward-uuid-' . $id);
        $forward->method('getUrl')->willReturn($url);

        return $forward;
    }

    private function createService(bool $clickTrack = true): LinkTrackService
    {
        return new LinkTrackService(
            $this->createMock(LinkTrackRepository::class),
            $this->forwardRepository,
            $this->mlRepository,
            $this->tokenService,
            new ParameterProvider(['click_track' => $clickTrack]),
        );
    }

    /** @return string[] the uuids found in all tracking links of the text, in order */
    private function trackedForwardUuids(string $text): array
    {
        preg_match_all('~/lt\?tid=([A-Za-z0-9_-]+)~', $text, $matches);

        return array_map(
            fn (string $tid): string => $this->tokenService->parse($tid)->forwardUuid,
            $matches[1]
        );
    }

    public function testIsClickTrackingEnabledFollowsTheParameter(): void
    {
        self::assertTrue($this->createService(true)->isClickTrackingEnabled());
        self::assertFalse($this->createService(false)->isClickTrackingEnabled());
    }

    public function testContentIsReturnedUntouchedWhenClickTrackingIsDisabled(): void
    {
        $content = new MessagePrecacheDto();
        $content->content = '<a href="https://example.com/">x</a>';

        $this->forwardRepository->expects(self::never())->method('findOrCreateByUrl');

        $result = $this->createService(false)->trackLinks($content, $this->campaign, $this->subscriber);

        self::assertSame($content, $result);
    }

    public function testContentIsReturnedUntouchedWhenCampaignHasNoUuid(): void
    {
        $campaign = $this->createMock(Message::class);
        $campaign->method('getUuid')->willReturn(null);
        $content = new MessagePrecacheDto();
        $content->content = '<a href="https://example.com/">x</a>';

        self::assertSame($content, $this->createService()->trackLinks($content, $campaign, $this->subscriber));
    }

    public function testHtmlLinksAreReplacedWithTrackingLinks(): void
    {
        $content = new MessagePrecacheDto();
        $content->content = '<p><a class="btn" href="https://example.com/a?x=1&amp;y=2" target="_blank">A</a> '
            . "<a href='http://example.org/b'><img src=\"b.png\"></a></p>";

        $result = $this->createService()->trackLinks($content, $this->campaign, $this->subscriber);

        self::assertStringContainsString('<a class="btn" href="https://example.com/api/v2/lt?tid=', $result->content);
        self::assertStringContainsString(' target="_blank">A</a>', $result->content);
        self::assertStringContainsString("<a href='https://example.com/api/v2/lt?tid=", $result->content);
        self::assertStringNotContainsString('example.org', $result->content);
        self::assertArrayHasKey('https://example.com/a?x=1&y=2', $this->createdForwards, 'stored url is decoded');
        self::assertArrayHasKey('http://example.org/b', $this->createdForwards);
    }

    public function testTrackingLinkIdentifiesForwardCampaignAndSubscriber(): void
    {
        $content = new MessagePrecacheDto();
        $content->content = '<a href="https://example.com/a">A</a>';

        $result = $this->createService()->trackLinks($content, $this->campaign, $this->subscriber);

        preg_match('~tid=([A-Za-z0-9_-]+)~', $result->content, $match);
        $token = $this->tokenService->parse($match[1]);
        self::assertNotNull($token);
        self::assertTrue($token->isHtml());
        self::assertSame('forward-uuid-1', $token->forwardUuid);
        self::assertSame('campaign-uuid', $token->messageUuid);
        self::assertSame('subscriber-uuid', $token->subscriberUuid);
    }

    public function testLinksThatMustNotBeTrackedAreLeftAlone(): void
    {
        $html = implode(' ', [
            '<a href="mailto:bob@example.com">mail</a>',
            '<a href="[UNSUBSCRIBEURL]">unsubscribe</a>',
            '<a href="https://example.com/?e=[EMAIL]">placeholder</a>',
            '<a href="javascript:alert(1)">script</a>',
            '<a href="https://example.com/real">https://example.com/other</a>',
            '<a href="https://example.com/' . str_repeat('a', 300) . '">too long</a>',
            '<a href="https://example.com/api/v2/lt?tid=abc">already tracked</a>',
            '<a name="anchor">no href</a>',
        ]);
        $content = new MessagePrecacheDto();
        $content->content = $html;

        $this->forwardRepository->expects(self::never())->method('findOrCreateByUrl');
        $this->mlRepository->expects(self::never())->method('incrementTotal');

        $result = $this->createService()->trackLinks($content, $this->campaign, $this->subscriber);

        self::assertSame($html, $result->content);
    }

    public function testPersonalUidAndSessionParametersAreStrippedAndMarkTheForwardPersonal(): void
    {
        $content = new MessagePrecacheDto();
        $content->content = '<a href="https://example.com/p?a=1&amp;uid=[UID]&amp;PHPSESSID=x#top">P</a>';

        $this->createService()->trackLinks($content, $this->campaign, $this->subscriber);

        self::assertSame(
            ['https://example.com/p?a=1#top' => ['url' => 'https://example.com/p?a=1#top', 'personalise' => true]],
            $this->createdForwards
        );
    }

    public function testTextUrlsAreReplacedWithTextTokens(): void
    {
        $content = new MessagePrecacheDto();
        $content->textContent = "Read https://example.com/a. Or see http://example.org/b?x=1\nbye";
        $content->textFooter = 'More: https://example.com/a';

        $result = $this->createService()->trackLinks($content, $this->campaign, $this->subscriber);

        self::assertMatchesRegularExpression(
            '~^Read https://example.com/api/v2/lt\?tid=[A-Za-z0-9_-]+\. Or see https://example.com/api/v2/lt\?tid=~',
            $result->textContent
        );
        preg_match('~tid=([A-Za-z0-9_-]+)~', $result->textContent, $match);
        self::assertSame('T', $this->tokenService->parse($match[1])->type);
        self::assertSame(
            [$this->trackedForwardUuids($result->textContent)[0]],
            $this->trackedForwardUuids((string) $result->textFooter),
            'the same url gets the same forward'
        );
        self::assertSame("\nbye", substr($result->textContent, -4));
    }

    public function testFootersAreTrackedToo(): void
    {
        $content = new MessagePrecacheDto();
        $content->htmlFooter = '<a href="https://example.com/footer">Footer</a>';

        $result = $this->createService()->trackLinks($content, $this->campaign, $this->subscriber);

        self::assertSame(['forward-uuid-1'], $this->trackedForwardUuids($result->htmlFooter));
    }

    public function testTotalIsIncrementedOncePerDistinctLinkOfTheEmail(): void
    {
        $content = new MessagePrecacheDto();
        $content->content = '<a href="https://example.com/a">1</a><a href="https://example.com/a">2</a>'
            . '<a href="https://example.com/b">3</a>';
        $content->textContent = 'https://example.com/a';

        $incremented = [];
        $this->mlRepository->expects(self::exactly(2))
            ->method('incrementTotal')
            ->willReturnCallback(function (int $messageId, int $forwardId) use (&$incremented): void {
                $incremented[] = [$messageId, $forwardId];
            });

        $this->createService()->trackLinks($content, $this->campaign, $this->subscriber);

        self::assertSame([[7, 1], [7, 2]], $incremented);
    }

    public function testTheGivenContentIsNotModified(): void
    {
        $content = new MessagePrecacheDto();
        $content->content = '<a href="https://example.com/a">A</a>';

        $result = $this->createService()->trackLinks($content, $this->campaign, $this->subscriber);

        self::assertNotSame($content, $result);
        self::assertSame('<a href="https://example.com/a">A</a>', $content->content);
    }
}
