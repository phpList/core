<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Domain\Repository\Identity;

use Doctrine\ORM\EntityRepository;
use PhpList\PhpList4\Domain\Model\Identity\Administrator;
use PhpList\PhpList4\Security\HashGenerator;

/**
 * Repository for Administrator models.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class AdministratorRepository extends EntityRepository
{
    /**
     * Finds the Administrator with the given login credentials. Returns null if there is no match,
     * i.e., if the login credentials are incorrect.
     *
     * @param string $loginName
     * @param string $plainTextPassword
     *
     * @return Administrator|null
     */
    public function findOneByLoginCredentials(string $loginName, string $plainTextPassword)
    {
        // This will be solved via dependency injection later.
        $hashGenerator = new HashGenerator();
        $passwordHash = $hashGenerator->createPasswordHash($plainTextPassword);

        return $this->findOneBy(
            [
                'loginName' => $loginName,
                'passwordHash' => $passwordHash,
            ]
        );
    }
}
