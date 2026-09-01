<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Core\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use PhpList\Core\Core\Doctrine\SearchIndexDoctrineListener;
use PhpList\Core\Domain\Search\Message\IndexDocumentMessage;
use PhpList\Core\Domain\Search\Model\Interfaces\SearchIndexableInterface;
use PhpList\Core\Domain\Search\Model\SearchOperation;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class SearchIndexDoctrineListenerTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private EntityManagerInterface&MockObject $objectManager;
    private SearchIndexDoctrineListener $listener;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->objectManager = $this->createMock(EntityManagerInterface::class);
        $this->listener = new SearchIndexDoctrineListener($this->messageBus);
    }

    private function createIndexable(string $indexName, string $documentId, array $document): SearchIndexableInterface
    {
        $entity = $this->createMock(SearchIndexableInterface::class);
        $entity->method('getSearchIndexName')->willReturn($indexName);
        $entity->method('getSearchDocumentId')->willReturn($documentId);
        $entity->method('toSearchDocument')->willReturn($document);

        return $entity;
    }

    public function testPostPersistDoesNotDispatchBeforePostFlush(): void
    {
        $entity = $this->createIndexable('subscriber_history', '1', ['id' => 1]);

        $this->messageBus->expects($this->never())->method('dispatch');

        $this->listener->postPersist(new PostPersistEventArgs($entity, $this->objectManager));
    }

    public function testPostFlushDispatchesBufferedIndexMessage(): void
    {
        $entity = $this->createIndexable('subscriber_history', '1', ['id' => 1]);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (IndexDocumentMessage $message): bool {
                return $message->getIndexName() === 'subscriber_history'
                    && $message->getDocumentId() === '1'
                    && $message->getDocument() === ['id' => 1]
                    && $message->getOperation() === SearchOperation::Index;
            }))
            ->willReturn(new Envelope(new stdClass()));

        $this->listener->postPersist(new PostPersistEventArgs($entity, $this->objectManager));
        $this->listener->postFlush(new PostFlushEventArgs($this->objectManager));
    }

    public function testPostRemoveBuffersDeleteOperationWithEmptyDocument(): void
    {
        $entity = $this->createIndexable('subscriber_history', '1', ['id' => 1]);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (IndexDocumentMessage $message): bool {
                return $message->getOperation() === SearchOperation::Delete
                    && $message->getDocument() === [];
            }))
            ->willReturn(new Envelope(new stdClass()));

        $this->listener->postRemove(new PostRemoveEventArgs($entity, $this->objectManager));
        $this->listener->postFlush(new PostFlushEventArgs($this->objectManager));
    }

    public function testMultipleTouchesInOneFlushDedupeToOneDispatch(): void
    {
        $entity = $this->createIndexable('subscriber_history', '1', ['id' => 1]);

        $this->messageBus->expects($this->once())->method('dispatch')
            ->willReturn(new Envelope(new stdClass()));

        $this->listener->postPersist(new PostPersistEventArgs($entity, $this->objectManager));
        $this->listener->postUpdate(new PostUpdateEventArgs($entity, $this->objectManager));
        $this->listener->postFlush(new PostFlushEventArgs($this->objectManager));
    }

    public function testPostFlushWithNothingBufferedDoesNotDispatch(): void
    {
        $this->messageBus->expects($this->never())->method('dispatch');

        $this->listener->postFlush(new PostFlushEventArgs($this->objectManager));
    }

    public function testNonSearchIndexableEntityIsIgnored(): void
    {
        $entity = new stdClass();

        $this->messageBus->expects($this->never())->method('dispatch');

        $this->listener->postPersist(new PostPersistEventArgs($entity, $this->objectManager));
        $this->listener->postFlush(new PostFlushEventArgs($this->objectManager));
    }

    public function testPendingBufferIsClearedAfterDispatch(): void
    {
        $entity = $this->createIndexable('subscriber_history', '1', ['id' => 1]);

        $this->messageBus->expects($this->once())->method('dispatch')
            ->willReturn(new Envelope(new stdClass()));

        $this->listener->postPersist(new PostPersistEventArgs($entity, $this->objectManager));
        $this->listener->postFlush(new PostFlushEventArgs($this->objectManager));

        // A second postFlush with nothing new queued must not re-dispatch the same message.
        $this->listener->postFlush(new PostFlushEventArgs($this->objectManager));
    }
}
