<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Domain\Repository\Identity;

use PhpList\PhpList4\Domain\Model\Identity\Administrator;
use PhpList\PhpList4\Domain\Repository\AbstractRepository;
use PhpList\PhpList4\Security\HashGenerator;

/**
 * Repository for Administrator models.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class AdministratorRepository extends AbstractRepository
{
    /**
     * @var HashGenerator
     */
    private $hashGenerator = null;

    /**
     * @param HashGenerator $hashGenerator
     * @required
     *
     * @return void
     */
    public function injectHashGenerator(HashGenerator $hashGenerator)
    {
        $this->hashGenerator = $hashGenerator;
    }

    /**
     * Finds the Administrator with the given login credentials. Returns null if there is no match,
     * i.e., if the login credentials are incorrect.
     *
     * This also checks that the administrator is a super user.
     *
     * @param string $loginName
     * @param string $plainTextPassword
     *
     * @return Administrator|null
     */
    public function findOneByLoginCredentials(string $loginName, string $plainTextPassword)
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
