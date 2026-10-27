<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Repository;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use PhpList\Core\Domain\Analytics\Model\LinkTrackForward;
use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;

class LinkTrackForwardRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

    public function findOneByUuid(string $uuid): ?LinkTrackForward
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }

    public function findOneByUrlHash(string $urlHash): ?LinkTrackForward
    {
        return $this->findOneBy(['urlHash' => $urlHash]);
    }

    /**
     * Returns the forward for a (cleaned) URL, creating it when it does not exist yet. Creation uses a plain
     * insert, so two workers racing on the same URL end up with the one row enforced by the urlhash unique key
     * instead of a closed entity manager - this is an exception of the usual workflow of creating entities in manager
     * classes.
     */
    public function findOrCreateByUrl(string $url, bool $personalise): LinkTrackForward
    {
        $urlHash = md5($url);

        $forward = $this->findOneByUrlHash($urlHash);
        if ($forward === null) {
            // false when a concurrent worker created it first, the select then finds that row
            $this->insert($url, $urlHash, $personalise);
            $forward = $this->findOneByUrlHash($urlHash);
        }

        if (empty($forward->getUuid())) {
            $forward->setUuid(self::generateUuid());
            $this->getEntityManager()->flush();
        }

        return $forward;
    }

    private function insert(string $url, string $urlHash, bool $personalise): bool
    {
        $metadata = $this->getClassMetadata();
        try {
            $this->getEntityManager()->getConnection()->insert(
                $metadata->getTableName(),
                [
                    $metadata->getColumnName('url') => $url,
                    $metadata->getColumnName('urlHash') => $urlHash,
                    $metadata->getColumnName('personalise') => $personalise,
                    $metadata->getColumnName('uuid') => self::generateUuid(),
                ],
                [$metadata->getColumnName('personalise') => Types::BOOLEAN],
            );
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    private static function generateUuid(): string
    {
        return bin2hex(random_bytes(18));
    }
}
