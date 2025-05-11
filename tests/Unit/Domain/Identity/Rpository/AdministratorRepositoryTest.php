<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Identity\Rpository;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Repository\AdministratorRepository;
use PHPUnit\Framework\TestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class AdministratorRepositoryTest extends TestCase
{
    private AdministratorRepository $subject;

    protected function setUp(): void
    {
        $entityManager = $this->createMock(EntityManager::class);
        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->name = Administrator::class;

        $this->subject = new AdministratorRepository($entityManager, $classMetadata);
    }

    public function testClassIsEntityRepository(): void
    {
        self::assertInstanceOf(EntityRepository::class, $this->subject);
    }
}
