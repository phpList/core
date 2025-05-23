<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service;

use PhpList\Core\Domain\Subscription\Model\Dto\ImportSubscriberDto;

class CsvRowToDtoMapper
{
    private const KNOWN_HEADERS = [
        'email', 'confirmed', 'blacklisted', 'html_email', 'disabled', 'extra_data',
    ];

    public function map(array $row): ImportSubscriberDto
    {
        $extraAttributes = array_filter($row, function ($key) {
            return !in_array($key, self::KNOWN_HEADERS, true);
        }, ARRAY_FILTER_USE_KEY);

        return new ImportSubscriberDto(
            email: trim($row['email'] ?? ''),
            confirmed: filter_var($row['confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN),
            blacklisted: filter_var($row['blacklisted'] ?? false, FILTER_VALIDATE_BOOLEAN),
            htmlEmail: filter_var($row['html_email'] ?? false, FILTER_VALIDATE_BOOLEAN),
            disabled: filter_var($row['disabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            extraData: $row['extra_data'] ?? null,
            extraAttributes: $extraAttributes
        );
    }
}

