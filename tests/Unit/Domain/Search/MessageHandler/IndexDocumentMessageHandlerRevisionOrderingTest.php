<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Search\MessageHandler;

use PhpList\Core\Domain\Search\Message\IndexDocumentMessage;
use PhpList\Core\Domain\Search\MessageHandler\IndexDocumentMessageHandler;
use PhpList\Core\Domain\Search\Model\SearchOperation;
use PhpList\Core\Domain\Search\Service\ElasticsearchIndexer;
use PhpList\Core\Tests\Unit\Domain\Search\Fake\InMemoryVersionedElasticsearchClient;
use PHPUnit\Framework\TestCase;

/**
 * Drives IndexDocumentMessageHandler through the real ElasticsearchIndexer against a fake client that
 * models Elasticsearch's `version_type: external_gte` semantics, so it exercises the full
 * revision-propagation path (message -> handler -> indexer -> client) rather than mocking away the
 * exact ordering guarantee under test.
 */
class IndexDocumentMessageHandlerRevisionOrderingTest extends TestCase
{
    private InMemoryVersionedElasticsearchClient $client;
    private IndexDocumentMessageHandler $handler;

    protected function setUp(): void
    {
        $this->client = new InMemoryVersionedElasticsearchClient();
        $this->handler = new IndexDocumentMessageHandler(new ElasticsearchIndexer($this->client, 'phplist_'));
    }

    public function testDelayedRetryOfOlderUpdateDoesNotOverwriteNewerUpdate(): void
    {
        // The update at revision 100 is dispatched first but, say, the Messenger transport fails
        // to deliver it until a later retry - meanwhile a newer update (revision 200) for the same
        // document is dispatched and processed first.
        ($this->handler)(new IndexDocumentMessage(
            'subscriber_history',
            '1',
            ['id' => 1, 'summary' => 'Updated'],
            SearchOperation::Index,
            200,
        ));

        // The retry of the older message finally lands.
        ($this->handler)(new IndexDocumentMessage(
            'subscriber_history',
            '1',
            ['id' => 1, 'summary' => 'Original'],
            SearchOperation::Index,
            100,
        ));

        $this->assertSame(
            ['id' => 1, 'summary' => 'Updated'],
            $this->client->getDocument('phplist_subscriber_history', '1'),
        );
    }

    public function testDelayedRetryOfOlderUpdateDoesNotResurrectDeletedDocument(): void
    {
        ($this->handler)(new IndexDocumentMessage(
            'subscriber_history',
            '1',
            ['id' => 1, 'summary' => 'Original'],
            SearchOperation::Index,
            100,
        ));

        // A delete for the same document, at a newer revision, is processed first.
        ($this->handler)(new IndexDocumentMessage(
            'subscriber_history',
            '1',
            [],
            SearchOperation::Delete,
            300,
        ));

        // The delayed retry of the stale update finally lands and must not resurrect the document.
        ($this->handler)(new IndexDocumentMessage(
            'subscriber_history',
            '1',
            ['id' => 1, 'summary' => 'Original'],
            SearchOperation::Index,
            150,
        ));

        $this->assertNull($this->client->getDocument('phplist_subscriber_history', '1'));
    }

    public function testNewerUpdateAfterADeleteIsStillApplied(): void
    {
        ($this->handler)(new IndexDocumentMessage(
            'subscriber_history',
            '1',
            [],
            SearchOperation::Delete,
            300,
        ));

        ($this->handler)(new IndexDocumentMessage(
            'subscriber_history',
            '1',
            ['id' => 1, 'summary' => 'Recreated'],
            SearchOperation::Index,
            400,
        ));

        $this->assertSame(
            ['id' => 1, 'summary' => 'Recreated'],
            $this->client->getDocument('phplist_subscriber_history', '1'),
        );
    }
}