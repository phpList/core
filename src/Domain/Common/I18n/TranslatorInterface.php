<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\I18n;

interface TranslatorInterface
{
    /**
     * Translate a message key with optional parameters.
     * @param string $key Message key (e.g., Messages::AUTH_NOT_AUTHORIZED)
     * @param array<string,string|int|float> $params Placeholder values (e.g., ['login' => 'admin'])
     * @param string|null $locale Optional locale (defaults to environment/app locale)
     */
    public function translate(string $key, array $params = [], ?string $locale = null): string;
}
