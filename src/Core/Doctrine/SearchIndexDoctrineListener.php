<?php

declare(strict_types=1);

namespace PhpList\Core\Core\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
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
 *
 * preRemove captures getSearchIndexName()/getSearchDocumentId() before the delete happens: for
 * entities with a Doctrine-generated id, UnitOfWork::executeDeletions() nulls the identifier before
 * postRemove fires, so reading it there would queue a delete with an empty document id.
 *
 * $enabled (elasticsearch.enabled, ELASTICSEARCH_ENABLED) is the write-side half of making
 * Elasticsearch fully optional: when false, every event method below is a no-op, so nothing is ever
 * queued to the async_search transport - see docs/ElasticsearchSearch.md. The read-side half is each
 * entity's *ConfigurableReader falling back to its Doctrine repository.
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::postRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
class SearchIndexDoctrineListener
{
    /** @var array<string, IndexDocumentMessage> */
    private array $pending = [];

    /** @var array<int, array{0: string, 1: string}> keyed by spl_object_id() */
    private array $removalKeys = [];

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly bool $enabled = true,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        if (!$this->enabled) {
            return;
        }

        $entity = $args->getObject();
        if (!$entity instanceof SearchIndexableInterface) {
            return;
        }

        $this->queue($entity, SearchOperation::Index, $entity->getSearchIndexName(), $entity->getSearchDocumentId());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        if (!$this->enabled) {
            return;
        }

        $entity = $args->getObject();
        if (!$entity instanceof SearchIndexableInterface) {
            return;
        }

        $this->queue($entity, SearchOperation::Index, $entity->getSearchIndexName(), $entity->getSearchDocumentId());
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        if (!$this->enabled) {
            return;
        }

        $entity = $args->getObject();
        if (!$entity instanceof SearchIndexableInterface) {
            return;
        }

        $this->removalKeys[spl_object_id($entity)] = [$entity->getSearchIndexName(), $entity->getSearchDocumentId()];
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        if (!$this->enabled) {
            return;
        }

        $entity = $args->getObject();
        if (!$entity instanceof SearchIndexableInterface) {
            return;
        }

        $objectId = spl_object_id($entity);
        [$indexName, $documentId] = $this->removalKeys[$objectId]
            ?? [$entity->getSearchIndexName(), $entity->getSearchDocumentId()];
        unset($this->removalKeys[$objectId]);

        $this->queue($entity, SearchOperation::Delete, $indexName, $documentId);
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

    private function queue(
        SearchIndexableInterface $entity,
        SearchOperation $operation,
        string $indexName,
        string $documentId,
    ): void {
        $key = $indexName . '|' . $documentId;
        $document = $operation === SearchOperation::Index ? $entity->toSearchDocument() : [];

        $this->pending[$key] = new IndexDocumentMessage(
            $indexName,
            $documentId,
            $document,
            $operation,
            $this->nextRevision(),
        );
    }

    /**
     * Wall-clock microseconds, not a per-process counter: a delayed Messenger retry carries the
     * revision assigned when it was originally queued, and must stay comparable against revisions
     * assigned by other PHP processes/workers for the same document so the indexer (via Elasticsearch
     * external versioning) can tell a stale retry apart from a newer write.
     */
    private function nextRevision(): int
    {
        return (int) (microtime(true) * 1_000_000);
    }
}
