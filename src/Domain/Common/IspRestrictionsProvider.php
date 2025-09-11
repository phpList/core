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
        if (!is_file($this->confPath) || !is_readable($this->confPath)) {
            return new IspRestrictions(null, null, null);
        }

        $contents = file_get_contents($this->confPath);
        if ($contents === false) {
            $this->logger->warning('Cannot read ISP restrictions file', ['path' => $this->confPath]);
            return new IspRestrictions(null, null, null);
        }

        $maxBatch = null;
        $minBatchPeriod = null;
        $lockFile = null;

        $raw = [];
        foreach (preg_split('/\R/', $contents) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
                continue;
            }
            $parts = explode('=', $line, 2);
            if (\count($parts) !== 2) {
                continue;
            }
            [$key, $val] = array_map('trim', $parts);
            $raw[$key] = $val;

            switch ($key) {
                case 'maxbatch':
                    if ($val !== '' && ctype_digit($val)) {
                        $maxBatch = (int) $val;
                    }
                    break;
                case 'minbatchperiod':
                    if ($val !== '' && ctype_digit($val)) {
                        $minBatchPeriod = (int) $val;
                    }
                    break;
                case 'lockfile':
                    if ($val !== '') {
                        $lockFile = $val;
                    }
                    break;
            }
        }

        if ($maxBatch !== null || $minBatchPeriod !== null || $lockFile !== null) {
            $this->logger->info('ISP restrictions detected', [
                'path' => $this->confPath,
                'maxbatch' => $maxBatch,
                'minbatchperiod' => $minBatchPeriod,
                'lockfile' => $lockFile,
            ]);
        }

        return new IspRestrictions($maxBatch, $minBatchPeriod, $lockFile, $raw);
    }
}
