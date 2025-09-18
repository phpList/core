<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Service;

use DateTime;
use PhpList\Core\Domain\Identity\Model\AdminPasswordRequest;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Repository\AdminPasswordRequestRepository;
use PhpList\Core\Domain\Identity\Repository\AdministratorRepository;
use PhpList\Core\Domain\Messaging\Message\PasswordResetMessage;
use PhpList\Core\Security\HashGenerator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class PasswordManager
{
    private const TOKEN_EXPIRY = '+24 hours';

    private AdminPasswordRequestRepository $passwordRequestRepository;
    private AdministratorRepository $administratorRepository;
    private HashGenerator $hashGenerator;
    private MessageBusInterface $messageBus;
    private TranslatorInterface $translator;

    public function __construct(
        AdminPasswordRequestRepository $passwordRequestRepository,
        AdministratorRepository $administratorRepository,
        HashGenerator $hashGenerator,
        MessageBusInterface $messageBus,
        TranslatorInterface $translator
    ) {
        $this->passwordRequestRepository = $passwordRequestRepository;
        $this->administratorRepository = $administratorRepository;
        $this->hashGenerator = $hashGenerator;
        $this->messageBus = $messageBus;
        $this->translator = $translator;
    }

    /**
     * Generates a password reset token for the administrator with the given email.
     * Returns the token that should be sent to the user via email.
     *
     * @param string $email The email of the administrator
     * @return string The generated token
     * @throws NotFoundHttpException If no administrator with the given email exists
     */
    public function generatePasswordResetToken(string $email): string
    {
        $administrator = $this->administratorRepository->findOneBy(['email' => $email]);
        if ($administrator === null) {
            $message = $this->translator->trans('Administrator not found');
            throw new NotFoundHttpException($message, null, 1500567100);
        }

        $existingRequests = $this->passwordRequestRepository->findByAdmin($administrator);
        foreach ($existingRequests as $request) {
            $this->passwordRequestRepository->remove($request);
        }

        $token = md5(random_bytes(256));

        $expiryDate = new DateTime(self::TOKEN_EXPIRY);
        $passwordRequest = new AdminPasswordRequest(date: $expiryDate, admin: $administrator, keyValue: $token);

        $this->passwordRequestRepository->save($passwordRequest);

        $message = new PasswordResetMessage(email: $email, token: $token);
        $this->messageBus->dispatch($message);

        return $token;
    }

    /**
     * Validates a password reset token.
     * Returns the administrator if the token is valid, null otherwise.
     *
     * @param string $token The token to validate
     * @return Administrator|null The administrator if the token is valid, null otherwise
     */
    public function validatePasswordResetToken(string $token): ?Administrator
    {
        $passwordRequest = $this->passwordRequestRepository->findOneByToken($token);
        if ($passwordRequest === null) {
            return null;
        }

        $now = new DateTime();
        if ($now >= $passwordRequest->getDate()) {
            $this->passwordRequestRepository->remove($passwordRequest);
            return null;
        }

        return $passwordRequest->getAdmin();
    }

    /**
     * Updates the password for the administrator with the given token.
     * Returns true if the password was updated successfully, false otherwise.
     *
     * @param string $token The password reset token
     * @param string $newPassword The new password
     * @return bool True if the password was updated successfully, false otherwise
     */
    public function updatePasswordWithToken(string $token, string $newPassword): bool
    {
        $administrator = $this->validatePasswordResetToken($token);
        if ($administrator === null) {
            return false;
        }

        $passwordHash = $this->hashGenerator->createPasswordHash($newPassword);
        $administrator->setPasswordHash($passwordHash);
        $this->administratorRepository->save($administrator);

        $passwordRequest = $this->passwordRequestRepository->findOneByToken($token);
        $this->passwordRequestRepository->remove($passwordRequest);

        return true;
    }

    /**
     * Cleans up expired password reset requests.
     */
    public function cleanupExpiredTokens(): void
    {
        $now = new DateTime();
        $allRequests = $this->passwordRequestRepository->findAll();
        foreach ($allRequests as $request) {
            if ($now >= $request->getDate()) {
                $this->passwordRequestRepository->remove($request);
            }
        }
    }
}
