<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common;

use Symfony\Component\HttpFoundation\RequestStack;

class SystemInfoCollector
{
    private RequestStack $requestStack;
    private array $configuredKeys;
    private array $defaultKeys = ['HTTP_USER_AGENT','HTTP_REFERER','REMOTE_ADDR','REQUEST_URI','HTTP_X_FORWARDED_FOR'];

    /**
     * @param string[] $configuredKeys keys to include (empty => use defaults)
     */
    public function __construct(
        RequestStack $requestStack,
        array $configuredKeys = []
    ) {
        $this->requestStack = $requestStack;
        $this->configuredKeys = $configuredKeys;
    }

    /**
     * Return key=>value pairs (already sanitized for safe logging/HTML display).
     *
     * @return array<string,string>
     */
    public function collect(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        $data = [];

        if ($request) {
            $headers = $request->headers;

            $data['HTTP_USER_AGENT'] = (string) $headers->get('User-Agent', '');
            $data['HTTP_REFERER'] = (string) $headers->get('Referer', '');
            $data['HTTP_X_FORWARDED_FOR'] = (string) $headers->get('X-Forwarded-For', '');
            $data['REQUEST_URI'] = $request->getRequestUri();
            $data['REMOTE_ADDR'] = $request->getClientIp() ?? '';
        } else {
            $server = $_SERVER;
            $data['HTTP_USER_AGENT'] = (string) ($server['HTTP_USER_AGENT'] ?? '');
            $data['HTTP_REFERER'] = (string) ($server['HTTP_REFERER'] ?? '');
            $data['HTTP_X_FORWARDED_FOR'] = (string) ($server['HTTP_X_FORWARDED_FOR'] ?? '');
            $data['REQUEST_URI'] = (string) ($server['REQUEST_URI'] ?? '');
            $data['REMOTE_ADDR'] = (string) ($server['REMOTE_ADDR'] ?? '');
        }

        $keys = $this->configuredKeys ?: $this->defaultKeys;

        $out = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $val = $data[$key];

            $safeKey = strip_tags($key);
            $safeVal = htmlspecialchars((string) $val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $out[$safeKey] = $safeVal;
        }

        return $out;
    }

    /**
     * Convenience to match the legacy multi-line string format.
     */
    public function collectAsString(): string
    {
        $pairs = $this->collect();
        if (!$pairs) {
            return '';
        }
        $lines = [];
        foreach ($pairs as $k => $v) {
            $lines[] = sprintf("%s = %s", $k, $v);
        }
        return "\n" . implode("\n", $lines);
    }
}
