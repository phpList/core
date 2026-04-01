<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Common;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Common\HtmlUrlRewriter;
use PhpList\Core\Domain\Common\RemotePageFetcher;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\UrlCache;
use PhpList\Core\Domain\Configuration\Repository\UrlCacheRepository;
use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class RemotePageFetcherTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    private CacheInterface&MockObject $cache;
    private ConfigProvider&MockObject $configProvider;
    private UrlCacheRepository&MockObject $urlCacheRepository;
    private EventLogManager&MockObject $eventLogManager;
    private HtmlUrlRewriter&MockObject $htmlUrlRewriter;
    private EntityManagerInterface&MockObject $entityManager;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->configProvider = $this->createMock(ConfigProvider::class);
        $this->urlCacheRepository = $this->createMock(UrlCacheRepository::class);
        $this->eventLogManager = $this->createMock(EventLogManager::class);
        $this->htmlUrlRewriter = $this->createMock(HtmlUrlRewriter::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
    }

    private function createFetcher(int $ttl = 300): RemotePageFetcher
    {
        return new RemotePageFetcher(
            httpClient: $this->httpClient,
            cache: $this->cache,
            configProvider: $this->configProvider,
            urlCacheRepository: $this->urlCacheRepository,
            eventLogManager: $this->eventLogManager,
            htmlUrlRewriter: $this->htmlUrlRewriter,
            entityManager: $this->entityManager,
            defaultTtl: $ttl,
        );
    }

    public function testReturnsContentFromPsrCacheWhenFresh(): void
    {
        $url = 'https://example.com/page?x=1&y=2';
        $this->configProvider->method('getValue')->with(ConfigOption::RemoteUrlAppend)->willReturn('');

        $cached = [
            'fetched' => time(),
            'content' => '<p>cached</p>',
        ];
        $this->cache->method('get')->with(md5($url))->willReturn($cached);

        $this->urlCacheRepository->expects($this->never())->method('findByUrlAndLastModified');
        $this->httpClient->expects($this->never())->method('request');

        $fetcher = $this->createFetcher();
        $result = $fetcher($url, []);

        $this->assertSame('<p>cached</p>', $result);
    }

    public function testReturnsContentFromDbCacheWhenFresh(): void
    {
        $url = 'https://ex.org/page';
        $this->configProvider->method('getValue')->with(ConfigOption::RemoteUrlAppend)->willReturn('');

        $this->cache->method('get')->with(md5($url))->willReturn(null);

        $recent = (new UrlCache())
            ->setUrl($url)
            ->setLastModified(time())
            ->setContent('<p>db</p>');

        $this->urlCacheRepository
            ->expects($this->once())
            ->method('findByUrlAndLastModified')
            ->with($url)
            ->willReturn($recent);

        $this->httpClient->expects($this->never())->method('request');

        $fetcher = $this->createFetcher();
        $result = $fetcher($url, []);

        $this->assertSame('<p>db</p>', $result);
    }

    public function testFetchesAndCachesWhenNoFreshCache(): void
    {
        $url = 'https://ex.net/a.html';
        $this->configProvider->method('getValue')->with(ConfigOption::RemoteUrlAppend)->willReturn('');

        $this->cache->method('get')->with(md5($url))->willReturn(null);

        $this->urlCacheRepository
            ->expects($this->atLeast(2))
            ->method('findByUrlAndLastModified')
            ->with($this->equalTo($url), $this->logicalOr($this->equalTo(0), $this->isType('int')))
            ->willReturnOnConsecutiveCalls(null, null);

        $this->urlCacheRepository->method('getByUrl')->with($url)->willReturn([]);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->with(false)->willReturn('<h1>hello</h1>');
        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with('GET', $url, $this->arrayHasKey('timeout'))
            ->willReturn($response);

        $this->htmlUrlRewriter
            ->expects($this->once())
            ->method('addAbsoluteResources')
            ->with('<h1>hello</h1>', $url)
            ->willReturn('rewritten:<h1>hello</h1>');

        $this->urlCacheRepository->expects($this->once())->method('persist')
            ->with($this->isInstanceOf(UrlCache::class));

        $this->cache->expects($this->once())->method('set')
            ->with(md5($url), $this->callback(function ($v) {
                return is_array($v)
                    && isset($v['fetched'], $v['content'])
                    && $v['content'] === 'rewritten:<h1>hello</h1>'
                    && is_int($v['fetched']);
            }));

        $this->eventLogManager->expects($this->atLeastOnce())->method('log');

        $fetcher = $this->createFetcher();
        $result = $fetcher($url, []);

        $this->assertSame('rewritten:<h1>hello</h1>', $result);
    }

    public function testHttpFailureReturnsEmptyStringAndNoCacheSet(): void
    {
        $url = 'https://bad.example/x';
        $this->configProvider->method('getValue')->with(ConfigOption::RemoteUrlAppend)->willReturn('');
        $this->cache->method('get')->with(md5($url))->willReturn(null);

        $this->urlCacheRepository->method('findByUrlAndLastModified')->willReturn(null);

        $this->httpClient->method('request')->willThrowException(new \RuntimeException('fail'));

        $this->cache->expects($this->never())->method('set');
        $this->entityManager->expects($this->never())->method('persist');
        $this->htmlUrlRewriter->expects($this->never())->method('addAbsoluteResources');

        $fetcher = $this->createFetcher();
        $result = $fetcher($url, []);

        $this->assertSame('', $result);
    }

    public function testUrlExpansionAndPlaceholderSubstitution(): void
    {
        $baseUrl = 'https://site.tld/path';

        $this->configProvider->method('getValue')->with(ConfigOption::RemoteUrlAppend)->willReturn('a=1&b=2');

        $this->cache->method('get')->willReturn(null);

        $this->urlCacheRepository->method('findByUrlAndLastModified')->willReturn(null);
        $this->urlCacheRepository->method('getByUrl')->willReturn([]);

        // After expansion, the code appends sanitized string directly. Because the URL already
        // contains a '?', append will be concatenated without an extra separator.

        // The invoke method replaces placeholders in URL prior to expansion.
        $urlWithPlaceholders = $baseUrl . '/[name]?q=[q]&amp;x=1';
        $userData = ['name' => 'John Doe', 'q' => 'a&b', 'password' => 'secret'];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->with(false)->willReturn('ok');
        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with($this->equalTo('GET'), $this->isType('string'), $this->arrayHasKey('timeout'))
            ->willReturn($response);

        $this->htmlUrlRewriter->method('addAbsoluteResources')->willReturnCallback(fn(string $html) => $html);

        $fetcher = $this->createFetcher();
        $result = $fetcher($urlWithPlaceholders, $userData);

        $this->assertSame('ok', $result);
    }
}
