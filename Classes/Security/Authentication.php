<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Security;

use Doctrine\ORM\EntityNotFoundException;
use PhpList\PhpList4\Domain\Model\Identity\Administrator;
use PhpList\PhpList4\Domain\Repository\Identity\AdministratorTokenRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * This class takes care of authenticating users and API clients.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class Authentication
{
    /**
     * @var AdministratorTokenRepository
     */
    private $tokenRepository = null;

    /**
     * Authentication constructor.
     *
     * @param AdministratorTokenRepository $tokenRepository
     */
    public function __construct(AdministratorTokenRepository $tokenRepository)
    {
        $this->tokenRepository = $tokenRepository;
    }

    /**
     * Tries to authenticate an administrator using the API key provided in the basic auth password contained in the
     * request headers. (The basic auth user will be ignored.)
     *
     * @param Request $request
     *
     * @return Administrator|null the authenticated Administrator or null if authentication has failed
     */
    public function authenticateByApiKey(Request $request)
    {
        $apiKey = $request->headers->get('php-auth-pw');
        if (empty($apiKey)) {
            return null;
        }

        $token = $this->tokenRepository->findOneUnexpiredByKey($apiKey);
        if ($token === null) {
            return null;
        }

        /** @var Administrator|null $administrator */
        $administrator = $token->getAdministrator();
        if ($administrator === null) {
            return null;
        }

        try {
            // Access any property to load the model from the database,
            // which will check that the model really exists in the database.
            $administrator->getLoginName();
        } catch (EntityNotFoundException $exception) {
            $administrator = null;
        }

        return $administrator;
    }
}
