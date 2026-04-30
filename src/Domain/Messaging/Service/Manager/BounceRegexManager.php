<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Manager;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Model\BounceRegex;
use PhpList\Core\Domain\Messaging\Model\BounceRegexBounce;
use PhpList\Core\Domain\Messaging\Repository\BounceRegexRepository;
use Symfony\Component\Validator\Exception\ValidatorException;

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
     * @throws ValidatorException
     */
    public function create(
        string $regex,
        Administrator $admin,
        ?string $action = null,
        ?int $listOrder = 0,
        ?string $comment = null,
        ?string $status = null
    ): BounceRegex {
        $regexHash = md5($regex);
        $existing = $this->bounceRegexRepository->findOneByRegexHash($regexHash);
        if ($existing !== null) {
            throw new ValidatorException('Bounce Regex already exists.');
        }

        $bounceRegex = new BounceRegex(
            regex: $regex,
            regexHash: $regexHash,
            action: $action,
            listOrder: $listOrder,
            adminId: $admin->getId(),
            comment: $comment,
            status: $status,
            count: 0
        );

        $this->bounceRegexRepository->persist($bounceRegex);

        return $bounceRegex;
    }

    public function update(
        BounceRegex $bounceRegex,
        string $regex,
        ?string $action = null,
        ?int $listOrder = 0,
        ?string $comment = null,
        ?string $status = null
    ): BounceRegex {
        $regexHash = md5($regex);
        $existing = $this->bounceRegexRepository->findOneByRegexHash($regexHash);
        if ($existing !== null && $existing->getId() !== $bounceRegex->getId()) {
            throw new ValidatorException('Bounce Regex already exists.');
        }

        $bounceRegex->setRegex($regex)
            ->setAction($action ?? $existing->getAction())
            ->setListOrder($listOrder ?? $existing->getListOrder())
            ->setRegexHash($regexHash)
            ->setComment($comment ?? $existing->getComment())
            ->setStatus($status ?? $existing->getStatus());

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
