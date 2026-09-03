# Elasticsearch-backed Search for Big Tables

This document explains the generic Elasticsearch dual-write/read infrastructure and its consumers:
`SubscriberHistory` (table `phplist_user_user_history`) and `UserMessageBounce`
(table `phplist_user_message_bounce`).

## Overview

Some tables grow too large for comfortable ad-hoc filtering/pagination straight off MySQL. For those
tables, phpList Core writes to the database **and** to Elasticsearch, but reads **only** from
Elasticsearch:

- **Writes** stay exactly as they are today (Doctrine `persist()`/`remove()`). A generic Doctrine
  event listener (`PhpList\Core\Core\Doctrine\SearchIndexDoctrineListener`) detects any entity that
  implements `SearchIndexableInterface` and asynchronously dispatches an indexing/deletion message via
  Symfony Messenger from Doctrine's `postFlush` event, i.e. after the ORM flush - not necessarily after
  a real commit. Callers that wrap `flush()` in their own explicit transaction (e.g.
  `SubscriberCsvImporter`) cause `postFlush` to fire before that outer transaction actually commits; see
  "Consistency model" below for why this is still safe.
- **Reads** for those entities go through a dedicated reader interface (e.g.
  `SubscriberHistoryReaderInterface`) that is aliased in DI to an Elasticsearch-backed implementation
  instead of the Doctrine repository.

This is deliberately generic: adding the next big table only requires implementing three small
interfaces (see "Adding a new searchable entity" below) - no changes to the dual-write plumbing.

## Consistency model

- Dual-write is **asynchronous**. Between a row being committed to MySQL and the `async_search` worker
  processing its queued message, a read from Elasticsearch will not yet reflect that row.
- Reads **hard-fail** if Elasticsearch is unreachable - there is no fallback to the database. Any
  Elasticsearch error is raised as `PhpList\Core\Domain\Search\Exception\SearchBackendUnavailableException`.
- Dispatch happens from `postFlush`, not `postPersist`/`postUpdate`/`postRemove` (those fire while the
  flush is still in progress), so a row that ends up rolled back is never indexed. For a plain
  `flush()` call with no surrounding transaction, `postFlush`'s own commit is the real commit, so
  dispatch does happen after the row is durably committed. When a caller instead wraps `flush()` in
  its own explicit transaction (e.g. `SubscriberCsvImporter`), `postFlush` fires before that outer
  transaction commits - but the `async_search` transport is a Doctrine queue on the same connection, so
  the queued message insert shares that same outer transaction: the row and its message still commit
  or roll back together. The remaining crash window is narrower than "commit vs. dispatch" - it's
  strictly between the outer transaction's real commit and the `async_search` worker consuming the
  message; if a process dies in that window, that one row is missed until the next
  `phplist:search:reindex` run.

Consumers of `phplist/core` that build UI on top of these read paths should plan for both of the above
(e.g. a brief "just added" staleness window, and handling a 5xx-equivalent from a search-unavailable
condition) rather than assuming synchronous consistency with the database.

## Configuration

Set in `.env` (see `.env.dist`):

```dotenv
ELASTICSEARCH_HOSTS=http://127.0.0.1:9200
ELASTICSEARCH_USERNAME=
ELASTICSEARCH_PASSWORD=
ELASTICSEARCH_CONNECT_TIMEOUT=2
ELASTICSEARCH_REQUEST_TIMEOUT=5
```

`ELASTICSEARCH_HOSTS` accepts a comma-separated list for multi-node clusters. The index prefix is
applied to every logical index alias (e.g. alias `subscriber_history` becomes index
`phplist_subscriber_history` with the default prefix), the same convention as `DATABASE_PREFIX` for
MySQL tables.

### Making Elasticsearch optional

Set `ELASTICSEARCH_ENABLED=false` to run without an Elasticsearch cluster at all - no code changes,
no cluster required, and it's safe even if `ELASTICSEARCH_HOSTS` is unreachable or unset:

- **Writes**: `SearchIndexDoctrineListener` becomes a no-op - nothing is ever queued to the
  `async_search` transport, so no `IndexDocumentMessage` accumulates unconsumed.
- **Reads**: every reader interface (`SubscriberHistoryReaderInterface`,
  `UserMessageBounceReaderInterface`, `UserMessageBounceReportReaderInterface`) is aliased to a small
  `*ConfigurableReader` that picks the Doctrine repository instead of the Elasticsearch reader - see
  `SubscriberHistoryConfigurableReader`/`UserMessageBounceConfigurableReader`/
  `UserMessageBounceReportConfigurableReader`.

The `elasticsearch/elasticsearch` PHP client package is still a hard Composer dependency of
`phplist/core` either way - disabling the feature at runtime doesn't remove the need to have that
package installed, only the need to have a reachable cluster.

## Queueing

Indexing/deletion messages are routed to a dedicated `async_search` Messenger transport
(`config/packages/messenger.yaml`) - the same Doctrine-backed queue table used by `async_email`, but a
distinct `queue_name` so the two workloads don't compete or block each other. Run a worker for it in
addition to the email worker:

```bash
bin/console messenger:consume async_search
```

As with `async_email`, run this as a background service (e.g. via Supervisor) in production.
`auto_setup` is disabled for this transport: the `messenger_messages` table must already exist (it's
created lazily by `async_email` on first use). On a fresh install that enables search before ever
sending an email, run `bin/console messenger:setup-transports` once.

## Console commands

```bash
# Create or update Elasticsearch indices (mappings) for every registered searchable entity.
# Safe to re-run - never drops or recreates an existing index.
bin/console phplist:search:init-indices [--index=<alias>]

# Backfill Elasticsearch from the database. Safe to re-run (indexing is an upsert by id).
bin/console phplist:search:reindex [<alias>] [--batch-size=500] [--last-id=0]
```

Run `phplist:search:init-indices` once per environment before the first `phplist:search:reindex`, and
again after adding a new searchable entity or changing a mapping.

## Adding a new searchable entity

1. Implement `PhpList\Core\Domain\Search\Model\Interfaces\SearchIndexableInterface` on the entity
   (`getSearchIndexName()`, `getSearchDocumentId()`, `toSearchDocument()`). This is what makes
   `SearchIndexDoctrineListener` dual-write it automatically - no other write-path changes needed.
2. Add an index definition (`SearchIndexDefinitionInterface`: alias + mapping + settings) under the
   entity's own `Service/Search` folder, following
   `PhpList\Core\Domain\Subscription\Service\Search\SubscriberHistoryIndexDefinition` - it's
   auto-tagged and picked up by `phplist:search:init-indices` via DI `_instanceof` autoconfiguration
   in `config/services/elasticsearch.yml`.
3. Add a reindex provider (`SearchReindexProviderInterface`: alias + `countAll()` + `fetchBatch()`)
   following `SubscriberHistoryReindexProvider` - likewise auto-tagged, picked up by
   `phplist:search:reindex`.
4. If reads should also move to Elasticsearch, introduce a reader interface for that entity (mirroring
   `SubscriberHistoryReaderInterface`) and an Elasticsearch-backed implementation (mirroring
   `SubscriberHistoryElasticsearchReader`). To keep Elasticsearch optional for the new entity too, also
   add a `*ConfigurableReader` (mirroring `SubscriberHistoryConfigurableReader`) that picks between the
   Doctrine repository and the Elasticsearch reader based on `elasticsearch.enabled`, and alias the
   interface to that instead of aliasing directly to either backend.

## Troubleshooting

- **Reads throwing `SearchBackendUnavailableException`**: check Elasticsearch is reachable at
  `ELASTICSEARCH_HOSTS` and that `phplist:search:init-indices` has been run.
- **New/updated rows not appearing in search results**: make sure a `messenger:consume async_search`
  worker is running; check `bin/console messenger:failed:show` for stuck messages.
- **Data drifted between MySQL and Elasticsearch**: re-run `bin/console phplist:search:reindex <alias>`
  - it's a safe, idempotent full backfill.
