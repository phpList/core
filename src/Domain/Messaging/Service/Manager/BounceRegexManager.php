<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Manager;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Model\BounceRegex;
use PhpList\Core\Domain\Messaging\Model\BounceRegexBounce;
use PhpList\Core\Domain\Messaging\Repository\BounceRegexRepository;

class BounceRegexManager
{
    private BounceRegexRepository $bounceRegexRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(
        BounceRegexRepository $bounceRegexRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->bounceRegexRepository = $bounceRegexRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * Creates or updates (if exists) a BounceRegex from a raw regex pattern.
     */
    public function createOrUpdateFromPattern(
        string $regex,
        ?string $action = null,
        ?int $listOrder = 0,
        ?int $adminId = null,
        ?string $comment = null,
        ?string $status = null
    ): BounceRegex {
        $regexHash = md5($regex);

        $existing = $this->bounceRegexRepository->findOneByRegexHash($regexHash);

        if ($existing !== null) {
            $existing->setRegex($regex)
                ->setAction($action ?? $existing->getAction())
                ->setListOrder($listOrder ?? $existing->getListOrder())
                ->setAdminId($adminId ?? $existing->getAdminId())
                ->setComment($comment ?? $existing->getComment())
                ->setStatus($status ?? $existing->getStatus());

            $this->bounceRegexRepository->save($existing);

            return $existing;
        }

        $bounceRegex = new BounceRegex(
            regex: $regex,
            regexHash: $regexHash,
            action: $action,
            listOrder: $listOrder,
            adminId: $adminId,
            comment: $comment,
            status: $status,
            count: 0
        );

        $this->bounceRegexRepository->save($bounceRegex);

        return $bounceRegex;
    }

    /** @return BounceRegex[] */
    public function getAll(): array
    {
        return $this->bounceRegexRepository->findAll();
    }

    public function getByHash(string $regexHash): ?BounceRegex
    {
        return $this->bounceRegexRepository->findOneByRegexHash($regexHash);
    }

    public function delete(BounceRegex $bounceRegex): void
    {
        $this->bounceRegexRepository->remove($bounceRegex);
    }

    /**
     * Associates a bounce with the regex it matched and increments usage count.
     */
    public function associateBounce(BounceRegex $regex, Bounce $bounce): BounceRegexBounce
    {
        $relation = new BounceRegexBounce($regex->getId() ?? 0, $bounce->getId() ?? 0);
        $this->entityManager->persist($relation);

        $regex->setCount(($regex->getCount() ?? 0) + 1);

        return $relation;
    }
}
