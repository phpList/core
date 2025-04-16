<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Repository\Messaging;

use DateTime;
use Doctrine\ORM\Tools\SchemaTool;
use PhpList\Core\Domain\Model\Identity\Administrator;
use PhpList\Core\Domain\Model\Messaging\Message;
use PhpList\Core\Domain\Model\Messaging\Message\MessageContent;
use PhpList\Core\Domain\Model\Messaging\Message\MessageFormat;
use PhpList\Core\Domain\Model\Messaging\Message\MessageMetadata;
use PhpList\Core\Domain\Model\Messaging\Message\MessageOptions;
use PhpList\Core\Domain\Model\Messaging\Message\MessageSchedule;
use PhpList\Core\Domain\Repository\Messaging\MessageRepository;
use PhpList\Core\TestingSupport\Traits\DatabaseTestTrait;
use PhpList\Core\TestingSupport\Traits\SimilarDatesAssertionTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class MessageRepositoryTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use SimilarDatesAssertionTrait;

    private MessageRepository $messageRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSchema();

        $this->messageRepository = self::getContainer()->get(MessageRepository::class);
    }

    protected function tearDown(): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        parent::tearDown();
    }

    public function testMessageIsPersistedAndFetchedCorrectly(): void
    {
        $admin = new Administrator();
        $this->entityManager->persist($admin);

        $message = new Message(
            new MessageFormat(true),
            new MessageSchedule(1, null, 3, null),
            new MessageMetadata('done'),
            new MessageContent('Hello world!'),
            new MessageOptions(),
            $admin
        );

        $this->entityManager->persist($message);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $foundMessages = $this->messageRepository->getByOwnerId($admin->getId());

        self::assertCount(1, $foundMessages);
        self::assertInstanceOf(Message::class, $foundMessages[0]);
        self::assertSame('done', $foundMessages[0]->getMetadata()->getStatus());
        self::assertSame('Hello world!', $foundMessages[0]->getContent()->getSubject());
    }

    public function testGetByOwnerIdReturnsOnlyOwnedMessages(): void
    {
        $admin1 = new Administrator();
        $admin2 = new Administrator();

        $this->entityManager->persist($admin1);
        $this->entityManager->persist($admin2);

        $msg1 = new Message(
            new MessageFormat(true),
            new MessageSchedule(1, null, 3, null),
            new MessageMetadata('done'),
            new MessageContent('Owned by Admin 1!'),
            new MessageOptions(),
            $admin1
        );

        $msg2 = new Message(
            new MessageFormat(true),
            new MessageSchedule(1, null, 3, null),
            new MessageMetadata(null),
            new MessageContent('Owned by Admin 2!'),
            new MessageOptions(),
            $admin2
        );

        $msg3 = new Message(
            new MessageFormat(true),
            new MessageSchedule(1, null, 3, null),
            new MessageMetadata(null),
            new MessageContent('Hello world!'),
            new MessageOptions(),
            null
        );

        $this->entityManager->persist($msg1);
        $this->entityManager->persist($msg2);
        $this->entityManager->persist($msg3);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $results = $this->messageRepository->getByOwnerId($admin1->getId());

        self::assertCount(1, $results);
        self::assertSame('Owned by Admin 1!', $results[0]->getContent()->getSubject());
    }

    public function testMessageTimestampsAreSetOnPersist(): void
    {
        $expectedDate = new DateTime();

        $message = new Message(
            new MessageFormat(true),
            new MessageSchedule(1, null, 3, null),
            new MessageMetadata(null),
            new MessageContent('Hello world!'),
            new MessageOptions(),
            null
        );

        $this->entityManager->persist($message);

        self::assertSimilarDates($expectedDate, $message->getModificationDate());
    }
}
