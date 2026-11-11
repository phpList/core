<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Service\Query;

class MatomoAnalyticsQuery implements AnalyticsQueryInterface
{
    public function trackingParameters(string $emailFormat, array $messageData): array
    {
        return [
            'pk_source' => $messageData['pk_source'] ?? null,
            'pk_medium' => $messageData['pk_medium'] ?? null,
            'pk_campaign' => $messageData['pk_campaign'] ?? null,
            'pk_content' => $emailFormat,
        ];
    }

    public function editableParameters(array $messageData): array
    {
        return [
            'pk_source' => 'phpList',
            'pk_medium' => 'email',
            'pk_campaign' => (string) ($messageData['subject'] ?? ''),
        ];
    }

    public function prefix(): string
    {
        return 'pk_';
    }
}
