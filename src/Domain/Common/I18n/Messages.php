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

    // Identity
    public const IDENTITY_ADMIN_NOT_FOUND = 'identity.admin_not_found';

    // Subscription
    public const SUBSCRIPTION_LIST_NOT_FOUND = 'subscription.list_not_found';
    public const SUBSCRIPTION_SUBSCRIBER_NOT_FOUND = 'subscription.subscriber_not_found';
    public const SUBSCRIPTION_NOT_FOUND_FOR_LIST_AND_SUBSCRIBER = 'subscription.not_found_for_list_and_subscriber';

    private function __construct()
    {
    }
}
