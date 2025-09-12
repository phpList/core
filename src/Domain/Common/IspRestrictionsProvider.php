<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common;

use PhpList\Core\Domain\Common\Model\IspRestrictions;
use Psr\Log\LoggerInterface;

class IspRestrictionsProvider
{
    public function __construct(
        private readonly string $confPath,
        private readonly LoggerInterface $logger,
    ) {}

    public function load(): IspRestrictions
    {
        $contents = $this->readConfigFile();
        if ($contents === null) {
            return new IspRestrictions(null, null, null);
        }

        [$raw, $maxBatch, $minBatchPeriod, $lockFile] = $this->parseContents($contents);

        $this->logIfDetected($maxBatch, $minBatchPeriod, $lockFile);

        return new IspRestrictions($maxBatch, $minBatchPeriod, $lockFile, $raw);
    }

    private function readConfigFile(): ?string
    {
        if (!is_file($this->confPath) || !is_readable($this->confPath)) {
            return null;
        }
        $contents = file_get_contents($this->confPath);
        if ($contents === false) {
            $this->logger->warning('Cannot read ISP restrictions file', ['path' => $this->confPath]);
            return null;
        }
        return $contents;
    }

    /**
     * @return array{0: array<string,string>, 1: ?int, 2: ?int, 3: ?string}
     */
    private function parseContents(string $contents): array
    {
        $maxBatch = null;
        $minBatchPeriod = null;
        $lockFile = null;
        $raw = [];

        foreach (preg_split('/\R/', $contents) as $line) {
            [$key, $val] = $this->parseLine($line);
            if ($key === null) {
                continue;
            }
            $raw[$key] = $val;
            [$maxBatch, $minBatchPeriod, $lockFile] = $this->applyKeyValue(
                $key,
                $val,
                $maxBatch,
                $minBatchPeriod,
                $lockFile
            );
        }

        return [$raw, $maxBatch, $minBatchPeriod, $lockFile];
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function parseLine(string $line): array
    {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
            return [null, ''];
        }
        $parts = explode('=', $line, 2);
        if (\count($parts) !== 2) {
            return [null, ''];
        }

        return array_map('trim', $parts);
    }

    /**
     * @param string $key
     * @param string $val
     * @param ?int $maxBatch
     * @param ?int $minBatchPeriod
     * @param ?string $lockFile
     * @return array{0: ?int, 1: ?int, 2: ?string}
     */
    private function applyKeyValue(
        string $key,
        string $val,
        ?int $maxBatch,
        ?int $minBatchPeriod,
        ?string $lockFile
    ): array {
        if ($key === 'maxbatch') {
            if ($val !== '' && ctype_digit($val)) {
                $maxBatch = (int) $val;
            }
            return [$maxBatch, $minBatchPeriod, $lockFile];
        }
        if ($key === 'minbatchperiod') {
            if ($val !== '' && ctype_digit($val)) {
                $minBatchPeriod = (int) $val;
            }
            return [$maxBatch, $minBatchPeriod, $lockFile];
        }
        if ($key === 'lockfile') {
            if ($val !== '') {
                $lockFile = $val;
            }
            return [$maxBatch, $minBatchPeriod, $lockFile];
        }
        return [$maxBatch, $minBatchPeriod, $lockFile];
    }

    private function logIfDetected(?int $maxBatch, ?int $minBatchPeriod, ?string $lockFile): void
    {
        if ($maxBatch !== null || $minBatchPeriod !== null || $lockFile !== null) {
            $this->logger->info('ISP restrictions detected', [
                'path' => $this->confPath,
                'maxbatch' => $maxBatch,
                'minbatchperiod' => $minBatchPeriod,
                'lockfile' => $lockFile,
            ]);
        }
    }
}
