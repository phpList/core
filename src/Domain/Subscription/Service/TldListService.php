<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Configuration\Model\Config;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Repository\ConfigRepository;
use PhpList\Core\Domain\Subscription\Model\Dto\TldRefreshResult;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Keeps the list of valid top level domains (IANA's tlds-alpha-by-domain.txt) in the `internet_tlds`
 * config item, like phplist3's refreshTlds(), and answers whether a TLD is on it.
 */
class TldListService
{
    public const SOURCE_URL = 'https://data.iana.org/TLD/tlds-alpha-by-domain.txt';
    private const REFETCH_TIMEOUT_SECONDS = 2592000;
// 30 days, as TLD_REFETCH_TIMEOUT in phplist3
    private const MIN_PLAUSIBLE_TLDS = 100;

    /** @var array<string, true>|null */
    private ?array $knownTlds = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ConfigRepository $configRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%/resources/tlds-alpha-by-domain.txt')]
        private readonly string $bundledListPath,
        private readonly string $sourceUrl = self::SOURCE_URL,
    ) {
    }

    /**
     * Downloads the list unless it was refreshed in the last 30 days (or $force is set).
     * As in phplist3 the sync time is stored before downloading, so a failing download is not retried
     * on every call.
     */
    public function refresh(bool $force = false): TldRefreshResult
    {
        $stored = $this->storedTlds();
        $lastSync = (int) $this->configRepository->findValueByItem(ConfigOption::TldLastSync->value);
        if (!$force && $stored !== [] && $lastSync + self::REFETCH_TIMEOUT_SECONDS > time()) {
            return new TldRefreshResult(count($stored), TldRefreshResult::SOURCE_SKIPPED);
        }

        $this->saveConfig(ConfigOption::TldLastSync, (string) time());
        $this->entityManager->flush();

        $remote = $this->fetchRemote();
        if ($remote !== null) {
            $this->storeList($remote);

            return new TldRefreshResult(count($remote), TldRefreshResult::SOURCE_REMOTE);
        }

        if ($stored === []) {
            $bundled = $this->bundledTlds();
            $this->storeList($bundled);

            return new TldRefreshResult(count($bundled), TldRefreshResult::SOURCE_BUNDLED);
        }

        return new TldRefreshResult(count($stored), TldRefreshResult::SOURCE_KEPT);
    }

    /** @return list<string> lowercase TLDs, from the stored list or else the bundled one */
    public function getTlds(): array
    {
        $stored = $this->storedTlds();
        if ($stored !== []) {
            return $stored;
        }

        return $this->bundledTlds();
    }

    public function isKnownTld(string $tld): bool
    {
        if ($this->knownTlds === null) {
            $this->knownTlds = array_fill_keys($this->getTlds(), true);
        }

        return isset($this->knownTlds[strtolower($tld)]);
    }

    /**
     * Parses IANA's file format: "# comment" header line, then one TLD per line.
     *
     * @return list<string> unique lowercase TLDs
     */
    public function parseList(string $content): array
    {
        $tlds = [];
        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            $line = strtolower(trim($line));
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $line) === 1) {
                $tlds[$line] = true;
            }
        }

        return array_keys($tlds);
    }

    /** @return list<string>|null null when the download failed or does not look like a TLD list */
    private function fetchRemote(): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $this->sourceUrl, ['timeout' => 30]);
            if ($response->getStatusCode() !== 200) {
                $this->logger->warning('TLD list download returned a non-200 status', [
                    'status' => $response->getStatusCode(),
                ]);

                return null;
            }
            $tlds = $this->parseList($response->getContent());
        } catch (Throwable $throwable) {
            $this->logger->warning('TLD list download failed', ['error' => $throwable->getMessage()]);

            return null;
        }

        if (count($tlds) < self::MIN_PLAUSIBLE_TLDS) {
            $this->logger->warning('Downloaded TLD list is too short, ignoring it', ['count' => count($tlds)]);

            return null;
        }

        return $tlds;
    }

    /** @return list<string> */
    private function bundledTlds(): array
    {
        $content = is_readable($this->bundledListPath) ? file_get_contents($this->bundledListPath) : false;
        if ($content === false) {
            $this->logger->error('Bundled TLD list is missing or unreadable', ['path' => $this->bundledListPath]);

            return [];
        }

        return $this->parseList($content);
    }

    /** @return list<string> */
    private function storedTlds(): array
    {
        $value = (string) $this->configRepository->findValueByItem(ConfigOption::InternetTlds->value);

        return $value === '' ? [] : array_values(array_filter(explode('|', strtolower($value))));
    }

    /** @param list<string> $tlds */
    private function storeList(array $tlds): void
    {
        $this->saveConfig(ConfigOption::InternetTlds, implode('|', $tlds));
        $this->entityManager->flush();
        $this->knownTlds = null;
    }

    private function saveConfig(ConfigOption $option, string $value): void
    {
        $config = $this->configRepository->findByKey($option->value);
        if ($config === null) {
            $config = (new Config())
                ->setKey($option->value)
                ->setEditable(false)
                ->setType('textarea');
            $this->configRepository->persist($config);
        }
        $config->setValue($value);
    }
}
