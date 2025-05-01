<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Repository\Identity;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PhpList\Core\Domain\Model\Identity\Administrator;
use PhpList\Core\Domain\Repository\AbstractRepository;
use PhpList\Core\Domain\Repository\CursorPaginationTrait;
use PhpList\Core\Security\HashGenerator;

/**
 * Repository for Administrator models.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class AdministratorRepository extends AbstractRepository
{
    use CursorPaginationTrait;

    private HashGenerator $hashGenerator;

    public function __construct(
        EntityManagerInterface $entityManager,
        ClassMetadata $class,
        HashGenerator $hashGenerator = null
    ) {
        parent::__construct($entityManager, $class);
        $this->hashGenerator = $hashGenerator ?? new HashGenerator();
    }

    /**
     * Finds the Administrator with the given login credentials. Returns null if there is no match,
     * i.e., if the login credentials are incorrect.
     *
     * This also checks that the administrator is a superuser.
     *
     * @param string $loginName
     * @param string $plainTextPassword
     *
     * @return Administrator|null
     */
    public function findOneByLoginCredentials(string $loginName, string $plainTextPassword): ?Administrator
    {
        $passwordHash = $this->hashGenerator->createPasswordHash($plainTextPassword);

        return $this->findOneBy(
            [
                'loginName' => $loginName,
                'passwordHash' => $passwordHash,
                'superUser' => true,
            ]
        );
    }
}
