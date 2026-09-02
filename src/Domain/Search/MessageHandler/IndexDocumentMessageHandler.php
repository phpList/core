<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Search\MessageHandler;

use PhpList\Core\Domain\Search\Message\IndexDocumentMessage;
use PhpList\Core\Domain\Search\Model\SearchOperation;
use PhpList\Core\Domain\Search\Service\ElasticsearchIndexerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class IndexDocumentMessageHandler
{
    public function __construct(private readonly ElasticsearchIndexerInterface $indexer)
    {
    }

    public function __invoke(IndexDocumentMessage $message): void
    {
        match ($message->getOperation()) {
            SearchOperation::Index => $this->indexer->index(
                $message->getIndexName(),
                $message->getDocumentId(),
                $message->getDocument(),
                $message->getRevision(),
            ),
            SearchOperation::Delete => $this->indexer->delete(
                $message->getIndexName(),
                $message->getDocumentId(),
                $message->getRevision(),
            ),
        };
    }
}
