<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Messaging\Repository;

use DateTime;
use Doctrine\ORM\Tools\SchemaTool;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Messaging\Model\Filter\MessageFilter;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\MessageContent;
use PhpList\Core\Domain\Messaging\Model\Message\MessageFormat;
use PhpList\Core\Domain\Messaging\Model\Message\MessageMetadata;
use PhpList\Core\Domain\Messaging\Model\Message\MessageOptions;
use PhpList\Core\Domain\Messaging\Model\Message\MessageSchedule;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
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
        $admin = (new Administrator())->setLoginName('t');
        $this->entityManager->persist($admin);

        $message = new Message(
            new MessageFormat(true, 'text'),
            new MessageSchedule(1, null, 3, null, null),
            new MessageMetadata(Message\MessageStatus::Sent),
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
        self::assertSame(Message\MessageStatus::Sent, $foundMessages[0]->getMetadata()->getStatus());
        self::assertSame('Hello world!', $foundMessages[0]->getContent()->getSubject());
    }

    public function testGetByOwnerIdReturnsOnlyOwnedMessages(): void
    {
        $admin1 = (new Administrator())->setLoginName('1');
        $admin2 = (new Administrator())->setLoginName('2');

        $this->entityManager->persist($admin1);
        $this->entityManager->persist($admin2);

        $msg1 = new Message(
            new MessageFormat(true, OutputFormat::Text->value),
            new MessageSchedule(1, null, 3, null, null),
            new MessageMetadata(Message\MessageStatus::Sent),
            new MessageContent('Owned by Admin 1!'),
            new MessageOptions(),
            $admin1
        );

        $msg2 = new Message(
            new MessageFormat(true, OutputFormat::Text->value),
            new MessageSchedule(1, null, 3, null, null),
            new MessageMetadata(null),
            new MessageContent('Owned by Admin 2!'),
            new MessageOptions(),
            $admin2
        );

        $msg3 = new Message(
            new MessageFormat(true, OutputFormat::Text->value),
            new MessageSchedule(1, null, 3, null, null),
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
            new MessageFormat(true, OutputFormat::Text->value),
            new MessageSchedule(1, null, 3, null, null),
            new MessageMetadata(null),
            new MessageContent('Hello world!'),
            new MessageOptions(),
            null
        );

        $this->entityManager->persist($message);

        self::assertSimilarDates($expectedDate, $message->getUpdatedAt());
    }

    private function persistMessage(
        Message\MessageStatus $status,
        string $subject,
        ?Administrator $owner = null
    ): Message {
        $message = new Message(
            new MessageFormat(true, OutputFormat::Text->value),
            new MessageSchedule(1, null, 3, null, null),
            new MessageMetadata($status),
            new MessageContent($subject),
            new MessageOptions(),
            $owner
        );

        $this->entityManager->persist($message);

        return $message;
    }

    public function testGetFilteredAfterIdIncludesOwnerlessMessagesForAnyAdmin(): void
    {
        $admin = (new Administrator())->setLoginName('owner-test-admin');
        $otherAdmin = (new Administrator())->setLoginName('other-admin');
        $this->entityManager->persist($admin);
        $this->entityManager->persist($otherAdmin);

        $this->persistMessage(Message\MessageStatus::Sent, 'Legacy unowned campaign');
        $this->persistMessage(Message\MessageStatus::Sent, 'My own campaign', $admin);
        $this->persistMessage(Message\MessageStatus::Sent, "Someone else's campaign", $otherAdmin);
        $this->entityManager->flush();
        $this->entityManager->clear();
        $admin = $this->entityManager->getRepository(Administrator::class)->find($admin->getId());

        $filter = (new MessageFilter())->setOwner($admin);
        $result = $this->messageRepository->getFilteredAfterId($filter);

        $subjects = array_map(
            static fn (Message $message) => $message->getContent()->getSubject(),
            $result->getItems()
        );
        self::assertContains('Legacy unowned campaign', $subjects);
        self::assertContains('My own campaign', $subjects);
        self::assertNotContains("Someone else's campaign", $subjects);
    }

    public function testGetFilteredAfterIdFiltersBySingleStatus(): void
    {
        $this->persistMessage(Message\MessageStatus::Draft, 'Draft one');
        $this->persistMessage(Message\MessageStatus::Sent, 'Sent one');
        $this->entityManager->flush();
        $this->entityManager->clear();

        $filter = (new MessageFilter())->setStatus('draft');
        $result = $this->messageRepository->getFilteredAfterId($filter);

        self::assertCount(1, $result->getItems());
        self::assertSame('Draft one', $result->getItems()[0]->getContent()->getSubject());
        self::assertSame(1, $result->getTotal());
    }

    public function testGetFilteredAfterIdFiltersByMultipleCommaSeparatedStatuses(): void
    {
        $this->persistMessage(Message\MessageStatus::Draft, 'Draft one');
        $this->persistMessage(Message\MessageStatus::Submitted, 'Submitted one');
        $this->persistMessage(Message\MessageStatus::Sent, 'Sent one');
        $this->entityManager->flush();
        $this->entityManager->clear();

        $filter = (new MessageFilter())->setStatus('draft,submitted');
        $result = $this->messageRepository->getFilteredAfterId($filter);

        self::assertCount(2, $result->getItems());
        self::assertSame(2, $result->getTotal());
    }

    public function testGetFilteredAfterIdDefaultsToAscendingOrder(): void
    {
        $first = $this->persistMessage(Message\MessageStatus::Sent, 'First');
        $second = $this->persistMessage(Message\MessageStatus::Sent, 'Second');
        $this->entityManager->flush();
        $this->entityManager->clear();

        $result = $this->messageRepository->getFilteredAfterId(new MessageFilter());

        self::assertSame($first->getId(), $result->getItems()[0]->getId());
        self::assertSame($second->getId(), $result->getItems()[1]->getId());
    }

    public function testGetFilteredAfterIdSortsDescendingAndCursorsBackward(): void
    {
        $first = $this->persistMessage(Message\MessageStatus::Sent, 'First');
        $second = $this->persistMessage(Message\MessageStatus::Sent, 'Second');
        $third = $this->persistMessage(Message\MessageStatus::Sent, 'Third');
        $this->entityManager->flush();
        $this->entityManager->clear();

        $filter = (new MessageFilter())->setSortOrder('desc')->setLimit(2);
        $firstPage = $this->messageRepository->getFilteredAfterId($filter);

        self::assertCount(2, $firstPage->getItems());
        self::assertSame($third->getId(), $firstPage->getItems()[0]->getId());
        self::assertSame($second->getId(), $firstPage->getItems()[1]->getId());
        self::assertSame(3, $firstPage->getTotal());

        $secondPageFilter = (new MessageFilter())
            ->setSortOrder('desc')
            ->setLimit(2)
            ->setLastId($firstPage->getItems()[1]->getId());
        $secondPage = $this->messageRepository->getFilteredAfterId($secondPageFilter);

        self::assertCount(1, $secondPage->getItems());
        self::assertSame($first->getId(), $secondPage->getItems()[0]->getId());
    }
}
