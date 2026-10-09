<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Search\Exception;

use RuntimeException;

/**
 * The one exception type that leaves this bounded context for any Elasticsearch failure (connection,
 * timeout, 4xx/5xx response). Callers - in this repo and in downstream consumers - catch this instead
 * of coupling to the vendor client's exception hierarchy.
 */
class SearchBackendUnavailableException extends RuntimeException
{
}
