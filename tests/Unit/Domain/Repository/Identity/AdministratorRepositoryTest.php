<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Repository\Identity;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use PhpList\Core\Domain\Repository\Identity\AdministratorRepository;
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
        $classMetadata->name = 'PhpList\Core\Domain\Model\Identity\Administrator';

        $this->subject = new AdministratorRepository($entityManager, $classMetadata);
    }

    public function testClassIsEntityRepository(): void
    {
        self::assertInstanceOf(EntityRepository::class, $this->subject);
    }
}
