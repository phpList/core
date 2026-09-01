<?php

declare(strict_types=1);

namespace PhpList\Core\Core\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use PhpList\Core\Domain\Search\Message\IndexDocumentMessage;
use PhpList\Core\Domain\Search\Model\Interfaces\SearchIndexableInterface;
use PhpList\Core\Domain\Search\Model\SearchOperation;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Dual-writes any SearchIndexableInterface entity to Elasticsearch, generically, without touching
 * every write call site (~15 managers/handlers persist SubscriberHistory today, plus a direct
 * removal loop in SubscriberDeletionService - this listener covers all of them by construction).
 *
 * postPersist/postUpdate/postRemove fire *inside* the still-open DB transaction (verified against
 * UnitOfWork::commit()), so operations are buffered here and only dispatched to Messenger from
 * postFlush, once the transaction has actually committed. This trades perfect atomicity (a crash
 * between commit and dispatch loses one message - repaired by `phplist:search:reindex`) for the far
 * more important guarantee of never indexing/deleting a row that was rolled back.
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
class SearchIndexDoctrineListener
{
    /** @var array<string, IndexDocumentMessage> */
    private array $pending = [];

    public function __construct(private readonly MessageBusInterface $messageBus)
    {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->queue($args->getObject(), SearchOperation::Index);
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->queue($args->getObject(), SearchOperation::Index);
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->queue($args->getObject(), SearchOperation::Delete);
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->pending === []) {
            return;
        }

        $messages = $this->pending;
        $this->pending = [];

        foreach ($messages as $message) {
            $this->messageBus->dispatch($message);
        }
    }

    private function queue(object $entity, SearchOperation $operation): void
    {
        if (!$entity instanceof SearchIndexableInterface) {
            return;
        }

        $key = $entity->getSearchIndexName() . '|' . $entity->getSearchDocumentId();
        $document = $operation === SearchOperation::Index ? $entity->toSearchDocument() : [];

        $this->pending[$key] = new IndexDocumentMessage(
            $entity->getSearchIndexName(),
            $entity->getSearchDocumentId(),
            $document,
            $operation,
        );
    }
}
