<?php

declare(strict_types=1);

namespace PhpList\Core\Security;

/**
 * This class provides functions for working with secure hashes.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class HashGenerator
{
    /**
     * Legacy algorithm that older password hashes in the database may still use.
     *
     * @var string
     */
    const LEGACY_PASSWORD_HASH_ALGORITHM = 'sha256';

    public function createPasswordHash(string $plainTextPassword): string
    {
        return password_hash($plainTextPassword, PASSWORD_DEFAULT);
    }

    /**
     * Checks a plaintext password against a stored hash.
     *
     * Hashes created by {@see createPasswordHash()} are verified with `password_verify()`.
     * As a fallback, this also accepts hashes created by the old, unsalted
     * sha256-based scheme, so administrators with pre-existing hashes can still log in.
     */
    public function verifyPassword(string $plainTextPassword, string $hash): bool
    {
        if (password_verify($plainTextPassword, $hash)) {
            return true;
        }

        return $this->isLegacyHash($hash)
            && hash_equals(hash(static::LEGACY_PASSWORD_HASH_ALGORITHM, $plainTextPassword), $hash);
    }

    /**
     * Checks whether $hash was created by the old, unsalted sha256-based scheme
     * rather than by {@see createPasswordHash()}.
     */
    public function isLegacyHash(string $hash): bool
    {
        return preg_match('/^[0-9a-f]{64}$/', $hash) === 1;
    }
}
