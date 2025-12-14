<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Repository;

use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Messaging\Model\TemplateImage;

class TemplateImageRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

    public function findByFilename(string $filename): ?TemplateImage
    {
        return $this->createQueryBuilder('ti')
            ->where('ti.filename = :filename')
            ->setParameter('filename', $filename)
            ->andWhere('ti.template = 0')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findById(int $id): ?TemplateImage
    {
        return $this->createQueryBuilder('ti')
            ->where('ti.id = :id')
            ->setParameter('id', $id)
            ->andWhere('ti.template = 0')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
