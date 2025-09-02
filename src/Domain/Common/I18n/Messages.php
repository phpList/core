<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\I18n;

/**
 * Centralized message keys to be used across the application.
 * These keys map to translation strings in resources/translations.
 */
final class Messages
{
    // Authentication / Authorization
    public const AUTH_NOT_AUTHORIZED = 'auth.not_authorized';
    public const AUTH_LOGIN_FAILED = 'auth.login_failed';
    public const AUTH_LOGIN_DISABLED = 'auth.login_disabled';

    private function __construct()
    {
    }
}
