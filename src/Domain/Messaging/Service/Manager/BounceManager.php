<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Manager;

use DateTime;
use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Repository\BounceRepository;

class BounceManager
{
    private BounceRepository $bounceRepository;

    public function __construct(BounceRepository $bounceRepository)
    {
        $this->bounceRepository = $bounceRepository;
    }

    public function create(
        ?DateTime $date = null,
        ?string $header = null,
        ?string $data = null,
        ?string $status = null,
        ?string $comment = null
    ): Bounce {
        $bounce = new Bounce(
            date: $date,
            header: $header,
            data: $data,
            status: $status,
            comment: $comment
        );

        $this->bounceRepository->save($bounce);

        return $bounce;
    }

    public function save(Bounce $bounce): void
    {
        $this->bounceRepository->save($bounce);
    }

    public function delete(Bounce $bounce): void
    {
        $this->bounceRepository->remove($bounce);
    }

    /** @return Bounce[] */
    public function getAll(): array
    {
        return $this->bounceRepository->findAll();
    }

    public function getById(int $id): ?Bounce
    {
        /** @var Bounce|null $found */
        $found = $this->bounceRepository->find($id);
        return $found;
    }
}
