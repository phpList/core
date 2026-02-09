<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Repository;

use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Messaging\Model\Attachment;
use PhpList\Core\Domain\Messaging\Model\MessageAttachment;

class AttachmentRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

    /**
     * @return Attachment[]
     */
    public function findAttachmentsForMessage(int $messageId): array
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('a')
            ->from(Attachment::class, 'a')
            ->innerJoin(
                MessageAttachment::class,
                'ma',
                'WITH',
                'ma.attachmentId = a.id'
            )
            ->where('ma.messageId = :messageId')
            ->setParameter('messageId', $messageId)
            ->getQuery()
            ->getResult();
    }
}
