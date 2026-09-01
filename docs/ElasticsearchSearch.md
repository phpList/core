# Elasticsearch-backed Search for Big Tables

This document explains the generic Elasticsearch dual-write/read infrastructure and its first
consumer, `SubscriberHistory` (table `phplist_user_user_history`).

## Overview

Some tables grow too large for comfortable ad-hoc filtering/pagination straight off MySQL. For those
tables, phpList Core writes to the database **and** to Elasticsearch, but reads **only** from
Elasticsearch:

- **Writes** stay exactly as they are today (Doctrine `persist()`/`remove()`). A generic Doctrine
  event listener (`PhpList\Core\Core\Doctrine\SearchIndexDoctrineListener`) detects any entity that
  implements `SearchIndexableInterface` and asynchronously dispatches an indexing/deletion message via
  Symfony Messenger, once the surrounding transaction has actually committed.
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
- If a process crashes between the database transaction committing and the message being dispatched
  (a narrow window - see `SearchIndexDoctrineListener`'s docblock for why the dispatch happens in
  `postFlush`, not `postPersist`/`postUpdate`/`postRemove`), that one row is missed until the next
  `phplist:search:reindex` run. Nothing is ever indexed for a row that was rolled back.

Consumers of `phplist/core` that build UI on top of these read paths should plan for both of the above
(e.g. a brief "just added" staleness window, and handling a 5xx-equivalent from a search-unavailable
condition) rather than assuming synchronous consistency with the database.

## Configuration

Set in `.env` (see `.env.dist`):

```
ELASTICSEARCH_HOSTS=http://127.0.0.1:9200
ELASTICSEARCH_USERNAME=
ELASTICSEARCH_PASSWORD=
ELASTICSEARCH_INDEX_PREFIX=phplist_
ELASTICSEARCH_CONNECT_TIMEOUT=2
ELASTICSEARCH_REQUEST_TIMEOUT=5
```

`ELASTICSEARCH_HOSTS` accepts a comma-separated list for multi-node clusters. The index prefix is
applied to every logical index alias (e.g. alias `subscriber_history` becomes index
`phplist_subscriber_history` with the default prefix), the same convention as `DATABASE_PREFIX` for
MySQL tables.

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
   `SubscriberHistoryElasticsearchReader`), then alias the interface to it in DI instead of the
   Doctrine repository.

## Troubleshooting

- **Reads throwing `SearchBackendUnavailableException`**: check Elasticsearch is reachable at
  `ELASTICSEARCH_HOSTS` and that `phplist:search:init-indices` has been run.
- **New/updated rows not appearing in search results**: make sure a `messenger:consume async_search`
  worker is running; check `bin/console messenger:failed:show` for stuck messages.
- **Data drifted between MySQL and Elasticsearch**: re-run `bin/console phplist:search:reindex <alias>`
  - it's a safe, idempotent full backfill.