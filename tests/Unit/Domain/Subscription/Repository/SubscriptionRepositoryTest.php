<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Repository;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use PhpList\Core\Domain\Subscription\Model\Subscription;
use PhpList\Core\Domain\Subscription\Repository\SubscriptionRepository;
use PHPUnit\Framework\TestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class SubscriptionRepositoryTest extends TestCase
{
    private SubscriptionRepository $subject;

    protected function setUp(): void
    {
        $entityManager = $this->createMock(EntityManager::class);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->name = Subscription::class;

        $this->subject = new SubscriptionRepository($entityManager, $classMetadata);
    }

    public function testClassIsEntityRepository(): void
    {
        self::assertInstanceOf(EntityRepository::class, $this->subject);
    }
}
