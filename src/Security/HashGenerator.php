<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Security;

/**
 * This class provides functions for working with secure hashes.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class HashGenerator
{
    /**
     * @var string
     */
    const PASSWORD_HASH_ALGORITHM = 'sha256';

    /**
     * @param string $plainTextPassword
     *
     * @return string
     */
    public function createPasswordHash(string $plainTextPassword): string
    {
        return hash(static::PASSWORD_HASH_ALGORITHM, $plainTextPassword);
    }
}
