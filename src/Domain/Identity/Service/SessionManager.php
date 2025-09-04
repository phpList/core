<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Service;

use PhpList\Core\Domain\Common\I18n\Messages;
use Symfony\Contracts\Translation\TranslatorInterface;
use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Identity\Model\AdministratorToken;
use PhpList\Core\Domain\Identity\Repository\AdministratorRepository;
use PhpList\Core\Domain\Identity\Repository\AdministratorTokenRepository;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class SessionManager
{
    private AdministratorTokenRepository $tokenRepository;
    private AdministratorRepository $administratorRepository;
    private EventLogManager $eventLogManager;
    private TranslatorInterface $translator;

    public function __construct(
        AdministratorTokenRepository $tokenRepository,
        AdministratorRepository $administratorRepository,
        EventLogManager $eventLogManager,
        TranslatorInterface $translator
    ) {
        $this->tokenRepository = $tokenRepository;
        $this->administratorRepository = $administratorRepository;
        $this->eventLogManager = $eventLogManager;
        $this->translator = $translator;
    }

    public function createSession(string $loginName, string $password): AdministratorToken
    {
        $administrator = $this->administratorRepository->findOneByLoginCredentials($loginName, $password);
        if ($administrator === null) {
            $entry = $this->translator->trans(Messages::AUTH_LOGIN_FAILED, ['login' => $loginName]);
            $this->eventLogManager->log('login', $entry);
            $message = $this->translator->trans(Messages::AUTH_NOT_AUTHORIZED);
            throw new UnauthorizedHttpException('', $message, null, 1500567098);
        }

        if ($administrator->isDisabled()) {
            $entry = $this->translator->trans(Messages::AUTH_LOGIN_DISABLED, ['login' => $loginName]);
            $this->eventLogManager->log('login', $entry);
            $message = $this->translator->trans(Messages::AUTH_NOT_AUTHORIZED);
            throw new UnauthorizedHttpException('', $message, null, 1500567099);
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
