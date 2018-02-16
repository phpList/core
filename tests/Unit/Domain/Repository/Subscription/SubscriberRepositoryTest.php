<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Unit\Domain\Repository\Subscription;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use PhpList\PhpList4\Domain\Repository\Subscription\SubscriberRepository;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ProphecySubjectInterface;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class SubscriberRepositoryTest extends TestCase
{
    /**
     * @var SubscriberRepository
     */
    private $subject = null;

    protected function setUp()
    {
        /** @var EntityManager|ProphecySubjectInterface $entityManager */
        $entityManager = $this->prophesize(EntityManager::class)->reveal();
        /** @var ClassMetadata|ProphecySubjectInterface $classDescriptor */
        $classDescriptor = $this->prophesize(ClassMetadata::class)->reveal();
        $this->subject = new SubscriberRepository($entityManager, $classDescriptor);
    }

    /**
     * @test
     */
    public function classIsEntityRepository()
    {
        static::assertInstanceOf(EntityRepository::class, $this->subject);
    }
}
