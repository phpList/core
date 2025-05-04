<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Repository\Messaging;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use PhpList\Core\Domain\Model\Subscription\SubscriberList;
use PhpList\Core\Domain\Repository\Subscription\SubscriberListRepository;
use PHPUnit\Framework\TestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class SubscriberListRepositoryTest extends TestCase
{
    private SubscriberListRepository $subject;

    protected function setUp(): void
    {
        $entityManager = $this->createMock(EntityManager::class);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->name = SubscriberList::class;

        $this->subject = new SubscriberListRepository($entityManager, $classMetadata);
    }

    public function testClassIsEntityRepository(): void
    {
        self::assertInstanceOf(EntityRepository::class, $this->subject);
    }
}
