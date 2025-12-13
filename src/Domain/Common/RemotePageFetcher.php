<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\UrlCache;
use PhpList\Core\Domain\Configuration\Repository\UrlCacheRepository;
use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use Psr\SimpleCache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class RemotePageFetcher
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly ConfigProvider $configProvider,
        private readonly UrlCacheRepository $urlCacheRepository,
        private readonly EventLogManager $eventLogManager,
        private readonly HtmlUrlRewriter $htmlUrlRewriter,
        private readonly EntityManagerInterface $entityManager,
        private readonly int $defaultTtl = 300,
    ) {
    }

    public function __invoke(string $url, array $userData): string
    {
        //# fix the Editor replacing & with &amp;
        $url = str_ireplace('&amp;', '&', $url);

        foreach ($userData as $key => $val) {
            if ($key !== 'password') {
                $url = utf8_encode(str_ireplace("[$key]", urlencode($val), utf8_decode($url)));
            }
        }

        $url = $this->expandUrl($url);
        $cacheKey = md5($url);

        $item = $this->cache->get($cacheKey);
        if ($item && is_array($item) && (time() - $item['fetched'] < $this->defaultTtl)) {
            return $item['content'];
        }

        $cacheUrl = $this->urlCacheRepository->findByUrlAndLastModified($url);
        $timeout = time() - ($cacheUrl?->getLastModified() ?? 0);
        if ($timeout < $this->defaultTtl) {
            return $cacheUrl->getContent();
        }

        //# relying on the last modified header doesn't work for many pages
        //# use current time instead
        //# see http://mantis.phplist.com/view.php?id=7684
        $lastModified = time();
        $cacheUrl = $this->urlCacheRepository->findByUrlAndLastModified($url, $lastModified);
        $content = $cacheUrl?->getContent();
        if ($cacheUrl) {
            // todo: check what the page should be for this log
            $this->eventLogManager->log(page: 'unknown page', entry: $url . ' was cached in database');
        } else {
            $content = $this->fetchUrlDirect($url);
        }

        if (!empty($content)) {
            $content = $this->htmlUrlRewriter->addAbsoluteResources($content, $url);
            $this->eventLogManager->log(page: 'unknown page', entry:'Fetching '.$url.' success');

            $caches = $this->urlCacheRepository->getByUrl($url);
            foreach ($caches as $cache) {
                $this->entityManager->remove($cache);
            }
            $urlCache = (new UrlCache())->setUrl($url)->setContent($content)->setLastModified($lastModified);
            $this->entityManager->persist($urlCache);

            $this->cache->set($cacheKey, [
                'fetched' => time(),
                'content' => $content,
            ]);
        }

        return $content;
    }

    private function fetchUrlDirect(string $url): string
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
//                'timeout' => 10,
                'timeout' => 600,
                'allowRedirects' => 1,
                'method' => 'HEAD',
            ]);

            return $response->getContent(false);
        } catch (Throwable $e) {
            return '';
        }
    }

    private function expandURL(string $url): string
    {
        $url_append = $this->configProvider->getValue(ConfigOption::RemoteUrlAppend);
        $url_append = strip_tags($url_append);
        $url_append = preg_replace('/\W/', '', $url_append);
        if ($url_append) {
            if (strpos($url, '?')) {
                $url = $url.$url_append;
            } else {
                $url = $url.'?'.$url_append;
            }
        }

        return $url;
    }
}

