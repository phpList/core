<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Security;

use Doctrine\ORM\Tools\SchemaTool;
use PhpList\Core\Security\HashGenerator;
use PhpList\Core\Tests\TestingSupport\Traits\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class HashGeneratorTest extends KernelTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSchema();
    }

    protected function tearDown(): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        parent::tearDown();
    }

    public function testSubjectIsAvailableViaContainer()
    {
        self::assertInstanceOf(HashGenerator::class, self::getContainer()->get(HashGenerator::class));
    }

    public function testClassIsRegisteredAsSingletonInContainer()
    {
        $id = HashGenerator::class;

        self::assertSame(self::getContainer()->get($id), self::getContainer()->get($id));
    }
}
