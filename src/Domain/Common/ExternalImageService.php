<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;

class ExternalImageService
{
    private string $externalCacheDir;

    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly string $tempDir,
        private readonly int $externalImageMaxAge,
        private readonly int $externalImageMaxSize,
        private readonly ?int $externalImageTimeout = 30,
    ) {
        $this->externalCacheDir = $this->tempDir . '/external_cache';
    }

    public function getFromCache(string $filename, int $messageId): ?string
    {
        $cacheFile = $this->generateLocalFileName($filename, $messageId);

        if (!is_file($cacheFile) || filesize($cacheFile) <= 64) {
            return null;
        }

        $content = file_get_contents($cacheFile);
        if ($content === false) {
            return null;
        }

        return base64_encode($content);
    }

    public function cache($filename, $messageId): bool
    {
        if (
            !(str_starts_with($filename, 'http'))
            || str_contains($filename, '://' . $this->configProvider->getValue(ConfigOption::Website) . '/')
        ) {
            return false;
        }

        if (!file_exists($this->externalCacheDir)) {
            @mkdir($this->externalCacheDir);
        }

        if (!file_exists($this->externalCacheDir) || !is_writable($this->externalCacheDir)) {
            return false;
        }

        $this->removeOldFilesInCache();

        $cacheFileName = $this->generateLocalFileName($filename, $messageId);

        if (!file_exists($cacheFileName)) {
            $cacheFileContent = null;

            if (function_exists('curl_init')) {
                $cacheFileContent = $this->downloadUsingCurl($filename);
            }

            if ($cacheFileContent === null) {
                $cacheFileContent = $this->downloadUsingFileGetContent($filename);
            }

            if ($this->externalImageMaxSize && (strlen($cacheFileContent) > $this->externalImageMaxSize)) {
                $cacheFileContent = 'MAX_SIZE';
            }

            $cacheFileHandle = @fopen($cacheFileName, 'wb');
            if ($cacheFileHandle !== false) {
                if (flock($cacheFileHandle, LOCK_EX)) {
                    fwrite($cacheFileHandle, $cacheFileContent);
                    fflush($cacheFileHandle);
                    flock($cacheFileHandle, LOCK_UN);
                }
                fclose($cacheFileHandle);
            }
        }

        if (file_exists($cacheFileName) && (@filesize($cacheFileName) > 64)) {
            return true;
        }

        return false;
    }

    private function removeOldFilesInCache(): void
    {
        $extCacheDirHandle = @opendir($this->externalCacheDir);
        if (!$this->externalImageMaxAge || !$extCacheDirHandle) {
           return;
        }

        while (($cacheFile = @readdir($extCacheDirHandle)) !== false) {
            // todo: make sure that this is what we need
            if (!str_starts_with($cacheFile, '.')) {
                $cacheFileMTime = @filemtime($this->externalCacheDir.'/'.$cacheFile);

                if (
                    is_numeric($cacheFileMTime)
                    && ($cacheFileMTime > 0)
                    && ((time() - $cacheFileMTime) > $this->externalImageMaxAge)
                ) {
                    @unlink($this->externalCacheDir.'/'.$cacheFile);
                }
            }
        }

        @closedir($extCacheDirHandle);
    }

    private function generateLocalFileName(string $filename, int $messageId): string
    {
        return $this->externalCacheDir
            . '/'
            . $messageId
            . '_'
            . preg_replace([ '~[\.][\.]+~Ui', '~[^\w\.]~Ui',], ['', '_'], $filename);
    }

    private function downloadUsingCurl(string $filename): ?string
    {
        $cURLHandle = curl_init($filename);

        if ($cURLHandle !== false) {
            curl_setopt($cURLHandle, CURLOPT_HTTPGET, true);
            curl_setopt($cURLHandle, CURLOPT_HEADER, 0);
            curl_setopt($cURLHandle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($cURLHandle, CURLOPT_TIMEOUT, $this->externalImageTimeout);
            curl_setopt($cURLHandle, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($cURLHandle, CURLOPT_MAXREDIRS, 10);
            curl_setopt($cURLHandle, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($cURLHandle, CURLOPT_FAILONERROR, true);

            $cacheFileContent = curl_exec($cURLHandle);

            $cURLErrNo = curl_errno($cURLHandle);
            $cURLInfo = curl_getinfo($cURLHandle);

            curl_close($cURLHandle);

            if ($cURLErrNo != 0) {
                $cacheFileContent = 'CURL_ERROR_' . $cURLErrNo;
            }
            if ($cURLInfo['http_code'] >= 400) {
                $cacheFileContent = 'HTTP_CODE_' . $cURLInfo['http_code'];
            }
        }

        return $cacheFileContent ?? null;
    }

    private function downloadUsingFileGetContent(string $filename): string
    {
        $remoteURLContext = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->externalImageTimeout,
                'max_redirects' => '10',
            ]
        ]);

        $cacheFileContent = file_get_contents($filename, false, $remoteURLContext);
        if ($cacheFileContent === false) {
            $cacheFileContent = 'FGC_ERROR';
        }

        return $cacheFileContent;
    }
}
