<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Repository;

use DateTimeInterface;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PhpList\Core\Domain\Common\Repository\AbstractRepository;

/**
 * Per campaign and link aggregates. Counters are changed with atomic statements so that concurrent sending
 * workers and clicking subscribers cannot lose updates. Column names come from the entity mapping.
 */
class LinkTrackMlRepository extends AbstractRepository
{
    /** Counts one more email of the campaign that contains the link. */
    public function incrementTotal(int $messageId, int $forwardId): void
    {
        $total = $this->column('total');

        $this->upsert(
            updateSql: sprintf('%1$s = COALESCE(%1$s, 0) + 1', $total),
            updateParams: [],
            insertColumns: [$total => 1],
            messageId: $messageId,
            forwardId: $forwardId,
        );
    }

    public function recordClick(int $messageId, int $forwardId, bool $isHtml, DateTimeInterface $now): void
    {
        $timestamp = $now->format('Y-m-d H:i:s');
        [$clicked, $firstClick, $latestClick, $htmlClicked, $textClicked] = array_map(
            $this->column(...),
            ['clicked', 'firstClick', 'latestClick', 'htmlClicked', 'textClicked']
        );

        $this->upsert(
            updateSql: sprintf(
                '%1$s = COALESCE(%1$s, 0) + 1, %2$s = COALESCE(%2$s, :now), %3$s = :now,'
                . ' %4$s = COALESCE(%4$s, 0) + :html, %5$s = COALESCE(%5$s, 0) + :text',
                $clicked,
                $firstClick,
                $latestClick,
                $htmlClicked,
                $textClicked
            ),
            updateParams: ['now' => $timestamp, 'html' => (int) $isHtml, 'text' => (int) !$isHtml],
            insertColumns: [
                $clicked => 1,
                $firstClick => $timestamp,
                $latestClick => $timestamp,
                $htmlClicked => (int) $isHtml,
                $textClicked => (int) !$isHtml,
            ],
            messageId: $messageId,
            forwardId: $forwardId,
        );
    }

    private function column(string $field): string
    {
        return $this->getClassMetadata()->getColumnName($field);
    }

    /**
     * @param string $updateSql the assignments of the UPDATE, without SET
     * @param array<string, int|string> $updateParams
     * @param array<string, int|string> $insertColumns column => value
     * @throws Exception
     */
    private function upsert(
        string $updateSql,
        array $updateParams,
        array $insertColumns,
        int $messageId,
        int $forwardId,
    ): void {
        $connection = $this->getEntityManager()->getConnection();
        $tableName = $this->getClassMetadata()->getTableName();
        $messageColumn = $this->column('messageId');
        $forwardColumn = $this->column('forwardId');

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = :messageId AND %s = :forwardId',
            $connection->quoteIdentifier($tableName),
            $updateSql,
            $messageColumn,
            $forwardColumn
        );
        $params = $updateParams + ['messageId' => $messageId, 'forwardId' => $forwardId];

        if ($connection->executeStatement($sql, $params) > 0) {
            return;
        }

        try {
            $connection->insert(
                $tableName,
                [$messageColumn => $messageId, $forwardColumn => $forwardId] + $insertColumns
            );
        } catch (UniqueConstraintViolationException) {
            // inserted by a concurrent request in the meantime
            $connection->executeStatement($sql, $params);
        }
    }
}
