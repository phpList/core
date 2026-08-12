<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Security;

use PhpList\Core\Security\HashGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class HashGeneratorTest extends TestCase
{
    private HashGenerator $subject;

    protected function setUp(): void
    {
        $this->subject = new HashGenerator();
    }

    public function testCreatePasswordHashCreatesPasswordHashCompatibleHash(): void
    {
        $hash = $this->subject->createPasswordHash('Portal');

        self::assertNotFalse(password_get_info($hash)['algo']);
    }

    public function testCreatePasswordHashCalledTwoTimesWithSamePasswordCreatesDifferentHashes(): void
    {
        $password = 'Aperture Science';

        $hash1 = $this->subject->createPasswordHash($password);
        $hash2 = $this->subject->createPasswordHash($password);

        self::assertNotSame($hash1, $hash2);
    }

    public function testVerifyPasswordForMatchingPasswordAndHashReturnsTrue(): void
    {
        $password = 'Cave Johnson';
        $hash = $this->subject->createPasswordHash($password);

        self::assertTrue($this->subject->verifyPassword($password, $hash));
    }

    public function testVerifyPasswordForNonMatchingPasswordAndHashReturnsFalse(): void
    {
        $hash = $this->subject->createPasswordHash('Mel');

        self::assertFalse($this->subject->verifyPassword('Cave Johnson', $hash));
    }

    public function testVerifyPasswordForMatchingPasswordAndLegacyHashReturnsTrue(): void
    {
        $password = 'Bazinga!';
        $legacyHash = hash(HashGenerator::LEGACY_PASSWORD_HASH_ALGORITHM, $password);

        self::assertTrue($this->subject->verifyPassword($password, $legacyHash));
    }

    public function testVerifyPasswordForNonMatchingPasswordAndLegacyHashReturnsFalse(): void
    {
        $legacyHash = hash(HashGenerator::LEGACY_PASSWORD_HASH_ALGORITHM, 'Bazinga!');

        self::assertFalse($this->subject->verifyPassword('wrong-password', $legacyHash));
    }

    public function testIsLegacyHashForSha256HashReturnsTrue(): void
    {
        $legacyHash = hash(HashGenerator::LEGACY_PASSWORD_HASH_ALGORITHM, 'Bazinga!');

        self::assertTrue($this->subject->isLegacyHash($legacyHash));
    }

    public function testIsLegacyHashForPasswordHashHashReturnsFalse(): void
    {
        $hash = $this->subject->createPasswordHash('Bazinga!');

        self::assertFalse($this->subject->isLegacyHash($hash));
    }
}
