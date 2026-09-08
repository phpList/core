<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Messaging\Model\Dto\DomainThrottleReservation;

class DomainThrottleStateRepository extends AbstractRepository
{
    /**
     * Atomically reserves one send slot for $domain in the given fixed window, so
     * concurrent workers share a single accurate per-domain quota instead of each
     * keeping its own count. Follows the same conditional-UPDATE-plus-affected-rows
     * pattern as MessageRepository::tryClaimForProcessing so it stays portable across
     * MySQL/PostgreSQL/SQLite (no vendor-specific upsert/locking syntax).
     */
    public function tryReserveSlot(string $domain, int $windowStart, int $batchSize): DomainThrottleReservation
    {
        $connection = $this->getEntityManager()->getConnection();
        $table = $connection->quoteIdentifier($this->getClassMetadata()->getTableName());

        if ($this->incrementSentIfAllowed($connection, $table, $domain, $windowStart, $batchSize)) {
            return new DomainThrottleReservation(allowed: true);
        }

        if ($this->rolloverWindow($connection, $table, $domain, $windowStart)) {
            return new DomainThrottleReservation(allowed: true);
        }

        if ($this->insertFirstRow($connection, $table, $domain, $windowStart)) {
            return new DomainThrottleReservation(allowed: true);
        }

        // Lost the insert race to another worker; its row may already have room in this
        // window, so give the increment one more try before concluding we're blocked.
        if ($this->incrementSentIfAllowed($connection, $table, $domain, $windowStart, $batchSize)) {
            return new DomainThrottleReservation(allowed: true);
        }

        return new DomainThrottleReservation(
            allowed: false,
            blockedAttempts: $this->incrementBlocked($connection, $table, $domain, $windowStart),
        );
    }

    public function resetBlockedCount(string $domain, int $windowStart): void
    {
        $connection = $this->getEntityManager()->getConnection();
        $table = $connection->quoteIdentifier($this->getClassMetadata()->getTableName());

        $connection->executeStatement(
            sprintf('UPDATE %s SET blocked_count = 0 WHERE domain = :domain AND window_start = :window', $table),
            ['domain' => $domain, 'window' => $windowStart]
        );
    }

    /** @phpstan-impure */
    private function incrementSentIfAllowed(
        Connection $connection,
        string $table,
        string $domain,
        int $windowStart,
        int $batchSize
    ): bool {
        $affected = $connection->executeStatement(
            sprintf(
                'UPDATE %s SET sent_count = sent_count + 1
                 WHERE domain = :domain AND window_start = :window AND sent_count < :batchSize',
                $table
            ),
            ['domain' => $domain, 'window' => $windowStart, 'batchSize' => $batchSize]
        );

        return $affected > 0;
    }

    /** @phpstan-impure */
    private function rolloverWindow(Connection $connection, string $table, string $domain, int $windowStart): bool
    {
        $affected = $connection->executeStatement(
            sprintf(
                'UPDATE %s SET window_start = :window, sent_count = 1, blocked_count = 0
                 WHERE domain = :domain AND window_start < :window',
                $table
            ),
            ['domain' => $domain, 'window' => $windowStart]
        );

        return $affected > 0;
    }

    /** @phpstan-impure */
    private function insertFirstRow(Connection $connection, string $table, string $domain, int $windowStart): bool
    {
        try {
            $connection->executeStatement(
                sprintf(
                    'INSERT INTO %s (domain, window_start, sent_count, blocked_count) VALUES (:domain, :window, 1, 0)',
                    $table
                ),
                ['domain' => $domain, 'window' => $windowStart]
            );

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    /** @phpstan-impure */
    private function incrementBlocked(Connection $connection, string $table, string $domain, int $windowStart): int
    {
        $connection->executeStatement(
            sprintf(
                'UPDATE %s SET blocked_count = blocked_count + 1 WHERE domain = :domain AND window_start = :window',
                $table
            ),
            ['domain' => $domain, 'window' => $windowStart]
        );

        return (int) $connection->fetchOne(
            sprintf('SELECT blocked_count FROM %s WHERE domain = :domain AND window_start = :window', $table),
            ['domain' => $domain, 'window' => $windowStart]
        );
    }
}
