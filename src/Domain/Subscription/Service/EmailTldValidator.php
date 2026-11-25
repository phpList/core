<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service;

/**
 * Checks that the top level domain of an email address is a delegated TLD, as phplist3's is_email() did
 * with the `internet_tlds` list. It does not check anything else about the address, use filter_var for that.
 */
class EmailTldValidator
{
    public function __construct(private readonly TldListService $tldListService)
    {
    }

    public function hasKnownTld(string $email): bool
    {
        $atPosition = strrpos($email, '@');
        if ($atPosition === false) {
            return false;
        }

        $domain = rtrim(substr($email, $atPosition + 1), '.');
        $dot = strrpos($domain, '.');
        if ($dot === false) {
            return false;
        }

        $tld = substr($domain, $dot + 1);
        if ($tld !== '' && mb_strlen($tld) !== strlen($tld) && function_exists('idn_to_ascii')) {
            $tld = idn_to_ascii($tld, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        }

        return is_string($tld) && $tld !== '' && $this->tldListService->isKnownTld($tld);
    }
}
