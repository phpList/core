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

    public function testCreatePasswordHashCreates64CharacterHash(): void
    {
        $hash = $this->subject->createPasswordHash('Portal');
        self::assertMatchesRegularExpression('/^[a-z0-9]{64}$/', $hash);
    }

    public function testCreatePasswordHashCalledTwoTimesWithSamePasswordCreatesSameHash(): void
    {
        $password = 'Aperture Science';

        $hash1 = $this->subject->createPasswordHash($password);
        $hash2 = $this->subject->createPasswordHash($password);

        self::assertSame($hash1, $hash2);
    }

    public function testCreatePasswordHashCalledTwoTimesWithDifferentPasswordsCreatesDifferentHashes(): void
    {
        $hash1 = $this->subject->createPasswordHash('Mel');
        $hash2 = $this->subject->createPasswordHash('Cave Johnson');

        self::assertNotSame($hash1, $hash2);
    }
}
