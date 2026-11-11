<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Service\Query;

class GoogleAnalyticsQuery implements AnalyticsQueryInterface
{
    public function trackingParameters(string $emailFormat, array $messageData): array
    {
        return [
            'utm_source' => $messageData['utm_source'] ?? null,
            'utm_medium' => $messageData['utm_medium'] ?? null,
            'utm_campaign' => $messageData['utm_campaign'] ?? null,
            'utm_content' => $emailFormat,
        ];
    }

    public function editableParameters(array $messageData): array
    {
        return [
            'utm_source' => 'phpList',
            'utm_medium' => 'email',
            'utm_campaign' => (string) ($messageData['subject'] ?? ''),
        ];
    }

    public function prefix(): string
    {
        return 'utm_';
    }
}
