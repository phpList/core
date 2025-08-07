<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Manager;

use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\UserBlacklist;
use PhpList\Core\Domain\Subscription\Model\UserBlacklistData;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Repository\UserBlacklistRepository;

class SubscriberBlacklistManager
{
    public function __construct(
        private readonly SubscriberRepository $subscriberRepository,
        private readonly UserBlacklistRepository $userBlacklistRepository,
    ) {
    }

    public function isEmailBlacklisted(string $email): bool
    {
        return $this->subscriberRepository->isEmailBlacklisted($email);
    }

    public function getBlacklistInfo(string $email): ?Subscriber
    {
        return $this->userBlacklistRepository->findBlacklistedByEmail($email);
    }
// 095-38-25-55
    public function addEmailToBlacklist(string $email, ?string $reasonData = null): void
    {
        $existing = $this->getBlacklistInfo($email);
        if ($existing) {
            return;
        }

        $blacklistEntry = new UserBlacklist();
        $blacklistEntry->setEmail($email);
        $blacklistEntry->setAdded(new \DateTime());

        $entityManager->persist($blacklistEntry);

        if ($reasonData !== null) {
            $blacklistData = new UserBlacklistData();
            $blacklistData->setEmail($email);
            $blacklistData->setName('reason'); // or a relevant name
            $blacklistData->setData($reasonData);
            $entityManager->persist($blacklistData);
        }

        $entityManager->flush();
    }

    public function removeEmailFromBlacklist(string $email)
    {
        $entityManager = $this->entityManager;

        $blacklistEntry = $entityManager->getRepository(UserBlacklist::class)->find($email);
        if ($blacklistEntry) {
            $entityManager->remove($blacklistEntry);
        }

        $blacklistData = $entityManager->getRepository(UserBlacklistData::class)->find($email);
        if ($blacklistData) {
            $entityManager->remove($blacklistData);
        }

        // Also, update the user record
        $userRepo = $entityManager->getRepository(Subscriber::class);
        $user = $userRepo->findOneBy(['email' => $email]);
        if ($user) {
            $user->setBlacklisted(false);
        }

        $entityManager->flush();
    }

    public function getBlacklistReason(string $email): ?string
    {
        $repository = $this->entityManager->getRepository(UserBlacklistData::class);
        $data = $repository->findOneBy(['email' => $email]);
        return $data ? $data->getData() : null;
    }

}
