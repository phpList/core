<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Repository;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PHPUnit\Framework\TestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class SubscriberRepositoryTest extends TestCase
{
    private SubscriberRepository $subject;

    protected function setUp(): void
    {
        $entityManager = $this->createMock(EntityManager::class);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->name = Subscriber::class;

        $this->subject = new SubscriberRepository($entityManager, $classMetadata);
    }

    public function testClassIsEntityRepository(): void
    {
        self::assertInstanceOf(EntityRepository::class, $this->subject);
    }
}
