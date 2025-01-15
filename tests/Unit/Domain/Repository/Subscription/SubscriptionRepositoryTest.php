<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Repository\Subscription;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use PhpList\Core\Domain\Repository\Subscription\SubscriptionRepository;
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
        $classMetadata->name = 'PhpList\Core\Domain\Model\Subscription\Subscription';

        $this->subject = new SubscriptionRepository($entityManager, $classMetadata);
    }

    public function testClassIsEntityRepository(): void
    {
        self::assertInstanceOf(EntityRepository::class, $this->subject);
    }
}
