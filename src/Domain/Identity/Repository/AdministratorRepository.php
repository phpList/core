<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Security\HashGenerator;

/**
 * Repository for Administrator models.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class AdministratorRepository extends AbstractRepository implements PaginatableRepositoryInterface
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
