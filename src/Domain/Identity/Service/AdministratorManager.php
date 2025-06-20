<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Service;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Model\Dto\CreateAdministratorDto;
use PhpList\Core\Domain\Identity\Model\Dto\UpdateAdministratorDto;
use PhpList\Core\Security\HashGenerator;

class AdministratorManager
{
    private EntityManagerInterface $entityManager;
    private HashGenerator $hashGenerator;

    public function __construct(EntityManagerInterface $entityManager, HashGenerator $hashGenerator)
    {
        $this->entityManager = $entityManager;
        $this->hashGenerator = $hashGenerator;
    }

    public function createAdministrator(CreateAdministratorDto $dto): Administrator
    {
        $administrator = new Administrator();
        $administrator->setLoginName($dto->loginName);
        $administrator->setEmail($dto->email);
        $administrator->setSuperUser($dto->isSuperUser);
        $hashedPassword = $this->hashGenerator->createPasswordHash($dto->password);
        $administrator->setPasswordHash($hashedPassword);
        $administrator->setPrivilegesFromArray($dto->privileges);

        $this->entityManager->persist($administrator);
        $this->entityManager->flush();

        return $administrator;
    }

    public function updateAdministrator(Administrator $administrator, UpdateAdministratorDto $dto): void
    {
        if ($dto->loginName !== null) {
            $administrator->setLoginName($dto->loginName);
        }
        if ($dto->email !== null) {
            $administrator->setEmail($dto->email);
        }
        if ($dto->superAdmin !== null) {
            $administrator->setSuperUser($dto->superAdmin);
        }
        if ($dto->password !== null) {
            $hashedPassword = $this->hashGenerator->createPasswordHash($dto->password);
            $administrator->setPasswordHash($hashedPassword);
        }
        $administrator->setPrivilegesFromArray($dto->privileges);

        $this->entityManager->flush();
    }

    public function deleteAdministrator(Administrator $administrator): void
    {
        $this->entityManager->remove($administrator);
        $this->entityManager->flush();
    }
}
