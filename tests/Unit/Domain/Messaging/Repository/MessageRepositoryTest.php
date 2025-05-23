<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Repository;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PHPUnit\Framework\TestCase;

/**
 * Testcase.
 *
 * @author Tatevik Grigoryan <tatevik@phplist.com>
 */
class MessageRepositoryTest extends TestCase
{
    private MessageRepository $subject;

    protected function setUp(): void
    {
        $entityManager = $this->createMock(EntityManager::class);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->name = MessageRepository::class;

        $this->subject = new MessageRepository($entityManager, $classMetadata);
    }

    public function testClassIsEntityRepository(): void
    {
        self::assertInstanceOf(EntityRepository::class, $this->subject);
    }
}
