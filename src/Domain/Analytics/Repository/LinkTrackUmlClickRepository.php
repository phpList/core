<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Repository;

use DateTimeInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;

class LinkTrackUmlClickRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

    /**
     * Counts a click of a subscriber on a link of a campaign, creating the row on the first click. Column names
     * come from the entity mapping.
     */
    public function recordClick(
        int $messageId,
        int $userId,
        int $forwardId,
        bool $isHtml,
        DateTimeInterface $now,
    ): void {
        $connection = $this->getEntityManager()->getConnection();
        $metadata = $this->getClassMetadata();
        $timestamp = $now->format('Y-m-d H:i:s');
        [$message, $user, $forward, $clicked, $firstClick, $latestClick, $htmlClicked, $textClicked] = array_map(
            $metadata->getColumnName(...),
            ['messageId', 'userId', 'forwardId', 'clicked', 'firstClick', 'latestClick', 'htmlClicked', 'textClicked']
        );

        $sql = sprintf(
            'UPDATE %4$s SET %5$s = COALESCE(%5$s, 0) + 1, %6$s = COALESCE(%6$s, :now), %7$s = :now,'
            . ' %8$s = COALESCE(%8$s, 0) + :html, %9$s = COALESCE(%9$s, 0) + :text'
            . ' WHERE %1$s = :messageId AND %2$s = :userId AND %3$s = :forwardId',
            $message,
            $user,
            $forward,
            $connection->quoteIdentifier($metadata->getTableName()),
            $clicked,
            $firstClick,
            $latestClick,
            $htmlClicked,
            $textClicked
        );
        $params = [
            'now' => $timestamp,
            'html' => (int) $isHtml,
            'text' => (int) !$isHtml,
            'messageId' => $messageId,
            'userId' => $userId,
            'forwardId' => $forwardId,
        ];

        if ($connection->executeStatement($sql, $params) > 0) {
            return;
        }

        try {
            $connection->insert($metadata->getTableName(), [
                $message => $messageId,
                $user => $userId,
                $forward => $forwardId,
                $firstClick => $timestamp,
                $latestClick => $timestamp,
                $clicked => 1,
                $htmlClicked => (int) $isHtml,
                $textClicked => (int) !$isHtml,
            ]);
        } catch (UniqueConstraintViolationException) {
            // inserted by a concurrent request in the meantime
            $connection->executeStatement($sql, $params);
        }
    }
}
