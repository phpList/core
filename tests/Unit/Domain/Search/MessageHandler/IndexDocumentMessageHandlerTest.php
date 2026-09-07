<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Search\MessageHandler;

use PhpList\Core\Domain\Search\Message\IndexDocumentMessage;
use PhpList\Core\Domain\Search\MessageHandler\IndexDocumentMessageHandler;
use PhpList\Core\Domain\Search\Model\SearchOperation;
use PhpList\Core\Domain\Search\Service\ElasticsearchIndexerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class IndexDocumentMessageHandlerTest extends TestCase
{
    private ElasticsearchIndexerInterface&MockObject $indexer;
    private IndexDocumentMessageHandler $handler;

    protected function setUp(): void
    {
        $this->indexer = $this->createMock(ElasticsearchIndexerInterface::class);
        $this->handler = new IndexDocumentMessageHandler($this->indexer);
    }

    public function testInvokeIndexesOnIndexOperation(): void
    {
        $document = ['id' => 1, 'summary' => 'hello'];
        $message = new IndexDocumentMessage('subscriber_history', '1', $document, SearchOperation::Index, 100);

        $this->indexer
            ->expects($this->once())
            ->method('index')
            ->with('subscriber_history', '1', $document, 100);
        $this->indexer->expects($this->never())->method('delete');

        ($this->handler)($message);
    }

    public function testInvokeDeletesOnDeleteOperation(): void
    {
        $message = new IndexDocumentMessage('subscriber_history', '1', [], SearchOperation::Delete, 100);

        $this->indexer
            ->expects($this->once())
            ->method('delete')
            ->with('subscriber_history', '1', 100);
        $this->indexer->expects($this->never())->method('index');

        ($this->handler)($message);
    }
}
