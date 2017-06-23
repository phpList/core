<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Unit\Domain\Repository\Identity;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use PhpList\PhpList4\Domain\Repository\Identity\AdministratorTokenRepository;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ProphecySubjectInterface;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class AdministratorTokenRepositoryTest extends TestCase
{
    /**
     * @var AdministratorTokenRepository
     */
    private $subject = null;

    protected function setUp()
    {
        /** @var EntityManager|ProphecySubjectInterface $entityManager */
        $entityManager = $this->prophesize(EntityManager::class)->reveal();
        /** @var ClassMetadata|ProphecySubjectInterface $classDescriptor */
        $classDescriptor = $this->prophesize(ClassMetadata::class)->reveal();
        $this->subject = new AdministratorTokenRepository($entityManager, $classDescriptor);
    }

    /**
     * @test
     */
    public function classIsEntityRepository()
    {
        self::assertInstanceOf(EntityRepository::class, $this->subject);
    }
}
