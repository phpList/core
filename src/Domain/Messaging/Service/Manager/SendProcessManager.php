<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Manager;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Messaging\Model\SendProcess;
use PhpList\Core\Domain\Messaging\Repository\SendProcessRepository;

class SendProcessManager
{
    private SendProcessRepository $repository;
    private EntityManagerInterface $entityManager;

    public function __construct(SendProcessRepository $repository, EntityManagerInterface $entityManager)
    {
        $this->repository = $repository;
        $this->entityManager = $entityManager;
    }

    public function create(string $page, string $processIdentifier): SendProcess
    {
        $sendProcess = new SendProcess();
        $sendProcess->setStartedDate(new DateTime('now'));
        $sendProcess->setAlive(1);
        $sendProcess->setIpaddress($processIdentifier);
        $sendProcess->setPage($page);

        $this->entityManager->persist($sendProcess);
        $this->entityManager->flush();

        return $sendProcess;
    }


    /**
     * @return array{id:int, age:int}|null
     */
    public function findNewestAliveWithAge(string $page): ?array
    {
        $row = $this->repository->findNewestAlive($page);

        if (!$row instanceof SendProcess) {
            return null;
        }

        $modified = $row->getUpdatedAt();
        $age = $modified ? max(0, time() - (int)$modified->format('U')) : 0;

        return [
            'id'  => $row->getId(),
            'age' => $age,
        ];
    }
}
