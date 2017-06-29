<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Unit\Security;

use PhpList\PhpList4\Security\HashGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class HashGeneratorTest extends TestCase
{
    /**
     * @var HashGenerator
     */
    private $subject = null;

    protected function setUp()
    {
        $this->subject = new HashGenerator();
    }

    /**
     * @test
     */
    public function createPasswordHashCreates32ByteHash()
    {
        self::assertRegExp('/^[a-z0-9]{64}$/', $this->subject->createPasswordHash('Portal'));
    }

    /**
     * @test
     */
    public function createPasswordHashCalledTwoTimesWithSamePasswordCreatesSameHash()
    {
        $password = 'Aperture Science';

        self::assertSame($this->subject->createPasswordHash($password), $this->subject->createPasswordHash($password));
    }

    /**
     * @test
     */
    public function createPasswordHashCalledTwoTimesWithDifferentPasswordsCreatesDifferentHashes()
    {
        self::assertNotSame(
            $this->subject->createPasswordHash('Mel'),
            $this->subject->createPasswordHash('Cave Johnson')
        );
    }
}
