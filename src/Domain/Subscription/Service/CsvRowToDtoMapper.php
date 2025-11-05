<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service;

use PhpList\Core\Domain\Subscription\Model\Dto\ImportSubscriberDto;

class CsvRowToDtoMapper
{
    private const FK_HEADER = 'foreignkey';
    private const KNOWN_HEADERS = [
        'email', 'confirmed', 'blacklisted', 'html_email', 'disabled', 'extra_data', 'foreignkey',
    ];

    public function map(array $row): ImportSubscriberDto
    {
        // Normalize keys to lower-case for header matching safety (CSV library keeps original headers)
        $normalizedRow = $this->normalizeData($row);

        $email = strtolower(trim((string)($normalizedRow['email'] ?? '')));

        if (array_key_exists(self::FK_HEADER, $normalizedRow) && $normalizedRow[self::FK_HEADER] !== '') {
            $foreignKey = (string)$normalizedRow[self::FK_HEADER];
        }

        $extraAttributes = array_filter($normalizedRow, function ($key) {
            return !in_array($key, self::KNOWN_HEADERS, true);
        }, ARRAY_FILTER_USE_KEY);

        return new ImportSubscriberDto(
            email: $email,
            confirmed: filter_var($normalizedRow['confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN),
            blacklisted: filter_var($normalizedRow['blacklisted'] ?? false, FILTER_VALIDATE_BOOLEAN),
            htmlEmail: filter_var($normalizedRow['html_email'] ?? false, FILTER_VALIDATE_BOOLEAN),
            disabled: filter_var($normalizedRow['disabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            extraData: $normalizedRow['extra_data'] ?? null,
            extraAttributes: $extraAttributes,
            foreignKey: $foreignKey ?? null,
        );
    }

    private function normalizeData(array $row): array
    {
        $normalizedRow = [];
        foreach ($row as $key => $value) {
            $normalizedRow[strtolower((string)$key)] = is_string($value) ? trim($value) : $value;
        }

        return $normalizedRow;
    }
}
