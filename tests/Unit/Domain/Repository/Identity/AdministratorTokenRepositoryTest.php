<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Repository\Identity;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use PhpList\Core\Domain\Repository\Identity\AdministratorTokenRepository;
use PHPUnit\Framework\TestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class AdministratorTokenRepositoryTest extends TestCase
{
    private AdministratorTokenRepository $subject;

    protected function setUp(): void
    {
        $entityManager = $this->createMock(EntityManager::class);
        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->name = 'PhpList\Core\Domain\Model\Identity\AdministratorToken';

        $this->subject = new AdministratorTokenRepository($entityManager, $classMetadata);
    }

    public function testClassIsEntityRepository(): void
    {
        self::assertInstanceOf(EntityRepository::class, $this->subject);
    }
}
