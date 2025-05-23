<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Service;

use PhpList\Core\Domain\Identity\Model\AdministratorToken;
use PhpList\Core\Domain\Identity\Repository\AdministratorRepository;
use PhpList\Core\Domain\Identity\Repository\AdministratorTokenRepository;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class SessionManager
{
    private AdministratorTokenRepository $tokenRepository;
    private AdministratorRepository $administratorRepository;

    public function __construct(
        AdministratorTokenRepository $tokenRepository,
        AdministratorRepository $administratorRepository
    ) {
        $this->tokenRepository = $tokenRepository;
        $this->administratorRepository = $administratorRepository;
    }

    public function createSession(string $loginName, string $password): AdministratorToken
    {
        $administrator = $this->administratorRepository->findOneByLoginCredentials($loginName, $password);
        if ($administrator === null) {
            throw new UnauthorizedHttpException('', 'Not authorized', null, 1500567098);
        }

        $token = new AdministratorToken();
        $token->setAdministrator($administrator);
        $token->generateExpiry();
        $token->generateKey();
        $this->tokenRepository->save($token);

        return $token;
    }

    public function deleteSession(AdministratorToken $token): void
    {
        $this->tokenRepository->remove($token);
    }
}
