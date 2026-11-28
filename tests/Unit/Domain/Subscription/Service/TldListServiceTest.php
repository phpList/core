<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Configuration\Model\Config;
use PhpList\Core\Domain\Configuration\Repository\ConfigRepository;
use PhpList\Core\Domain\Subscription\Model\Dto\TldRefreshResult;
use PhpList\Core\Domain\Subscription\Service\TldListService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class TldListServiceTest extends TestCase
{
    private ConfigRepository&MockObject $configRepository;
    private EntityManagerInterface&MockObject $entityManager;
    /** @var array<string, Config> */
    private array $configs = [];
    private string $bundled;

    protected function setUp(): void
    {
        $this->bundled = tempnam(sys_get_temp_dir(), 'tlds');
        file_put_contents($this->bundled, "# Version 1\nCOM\nORG\nNET\n");

        $this->configs = [];
        $this->configRepository = $this->createMock(ConfigRepository::class);
        $this->configRepository->method('findValueByItem')
            ->willReturnCallback(fn (string $key) => $this->config($key)?->getValue());
        $this->configRepository->method('findByKey')
            ->willReturnCallback(fn (string $key) => $this->config($key));
        $this->configRepository->method('persist')
            ->willReturnCallback(function (Config $config): void {
                $this->configs[$config->getKey()] = $config;
            });
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        if (is_file($this->bundled)) {
            unlink($this->bundled);
        }
    }

    private function service(MockResponse|array $responses): TldListService
    {
        return new TldListService(
            httpClient: new MockHttpClient($responses),
            configRepository: $this->configRepository,
            entityManager: $this->entityManager,
            logger: new NullLogger(),
            bundledListPath: $this->bundled,
            sourceUrl: 'https://example.test/tlds.txt',
        );
    }

    private function remoteList(int $count = 150): string
    {
        $lines = ['# Version 2, Last Updated now'];
        for ($i = 0; $i < $count; $i++) {
            $lines[] = 'T' . $i . 'X';
        }
        $lines[] = 'XN--P1AI';

        return implode("\n", $lines) . "\n";
    }

    private function config(string $key): ?Config
    {
        return $this->configs[$key] ?? null;
    }

    private function stored(string $key): ?string
    {
        return $this->config($key)?->getValue();
    }

    public function testParseListSkipsCommentsBlankAndInvalidLinesAndKeepsPunycode(): void
    {
        $content = "# header\r\nCOM\n\n  Org \nxn--p1ai\nbad_one\n-bad\nbad-\nnot a tld\ncom\n";
        $tlds = $this->service([])->parseList($content);

        $this->assertSame(['com', 'org', 'xn--p1ai'], $tlds);
    }

    public function testGetTldsFallsBackToBundledListWhenNothingStored(): void
    {
        $service = $this->service([]);

        $this->assertSame(['com', 'org', 'net'], $service->getTlds());
        $this->assertTrue($service->isKnownTld('COM'));
        $this->assertFalse($service->isKnownTld('invalidtld'));
    }

    public function testRefreshStoresDownloadedList(): void
    {
        $service = $this->service(new MockResponse($this->remoteList()));
        $this->entityManager->expects($this->atLeastOnce())->method('flush');

        $result = $service->refresh();

        $this->assertSame(TldRefreshResult::SOURCE_REMOTE, $result->source);
        $this->assertSame(151, $result->count);
        $this->assertFalse($result->remoteFailed());
        $this->assertNotNull($this->stored('tld_last_sync'));
        $this->assertStringContainsString('t0x|t1x', (string) $this->stored('internet_tlds'));
        $this->assertTrue($service->isKnownTld('xn--p1ai'));
        $this->assertFalse($service->isKnownTld('com'));
    }

    public function testRefreshIsSkippedWhenDoneRecentlyUnlessForced(): void
    {
        $this->service(new MockResponse($this->remoteList()))->refresh();

        $skipped = $this->service([])->refresh();
        $this->assertSame(TldRefreshResult::SOURCE_SKIPPED, $skipped->source);
        $this->assertSame(151, $skipped->count);

        $forced = $this->service(new MockResponse($this->remoteList(120)))->refresh(true);
        $this->assertSame(TldRefreshResult::SOURCE_REMOTE, $forced->source);
        $this->assertSame(121, $forced->count);
    }

    public function testRefreshIsDueAgainAfterThirtyDays(): void
    {
        $this->service(new MockResponse($this->remoteList()))->refresh();
        $this->config('tld_last_sync')?->setValue((string) (time() - 2592001));

        $result = $this->service(new MockResponse($this->remoteList(120)))->refresh();

        $this->assertSame(TldRefreshResult::SOURCE_REMOTE, $result->source);
        $this->assertSame(121, $result->count);
    }

    public function testFailedDownloadSeedsBundledListWhenNothingStored(): void
    {
        $result = $this->service(new MockResponse('', ['http_code' => 500]))->refresh();

        $this->assertSame(TldRefreshResult::SOURCE_BUNDLED, $result->source);
        $this->assertSame(3, $result->count);
        $this->assertTrue($result->remoteFailed());
        $this->assertSame('com|org|net', $this->stored('internet_tlds'));
    }

    public function testFailedDownloadKeepsExistingList(): void
    {
        $this->service(new MockResponse($this->remoteList()))->refresh();
        $before = $this->stored('internet_tlds');

        $result = $this->service(new MockResponse('', ['http_code' => 503]))->refresh(true);

        $this->assertSame(TldRefreshResult::SOURCE_KEPT, $result->source);
        $this->assertTrue($result->remoteFailed());
        $this->assertSame($before, $this->stored('internet_tlds'));
    }

    public function testImplausiblyShortDownloadIsIgnored(): void
    {
        $result = $this->service(new MockResponse("# Version\nCOM\nORG\n"))->refresh();

        $this->assertSame(TldRefreshResult::SOURCE_BUNDLED, $result->source);
        $this->assertSame('com|org|net', $this->stored('internet_tlds'));
    }

    public function testSyncTimeIsStoredEvenWhenDownloadFails(): void
    {
        $this->service(new MockResponse('', ['http_code' => 500]))->refresh();

        $this->assertNotNull($this->stored('tld_last_sync'));
        $this->assertEqualsWithDelta(time(), (int) $this->stored('tld_last_sync'), 5);
    }
}
