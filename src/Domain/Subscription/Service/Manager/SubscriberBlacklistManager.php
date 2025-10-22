<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Manager;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Subscription\Model\UserBlacklist;
use PhpList\Core\Domain\Subscription\Model\UserBlacklistData;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Repository\UserBlacklistDataRepository;
use PhpList\Core\Domain\Subscription\Repository\UserBlacklistRepository;

class SubscriberBlacklistManager
{
    public function __construct(
        private readonly SubscriberRepository $subscriberRepository,
        private readonly UserBlacklistRepository $userBlacklistRepository,
        private readonly UserBlacklistDataRepository $blacklistDataRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function isEmailBlacklisted(string $email): bool
    {
        return $this->subscriberRepository->isEmailBlacklisted($email);
    }

    public function getBlacklistInfo(string $email): ?UserBlacklist
    {
        return $this->userBlacklistRepository->findBlacklistInfoByEmail($email);
    }

    public function addEmailToBlacklist(string $email, ?string $reasonData = null): UserBlacklist
    {
        $existing = $this->subscriberRepository->isEmailBlacklisted($email);
        if ($existing) {
            return $this->getBlacklistInfo($email);
        }

        $blacklistEntry = new UserBlacklist();
        $blacklistEntry->setEmail($email);
        $blacklistEntry->setAdded(new DateTime());

        $this->entityManager->persist($blacklistEntry);

        if ($reasonData !== null) {
            $blacklistData = new UserBlacklistData();
            $blacklistData->setEmail($email);
            $blacklistData->setName('reason');
            $blacklistData->setData($reasonData);
            $this->entityManager->persist($blacklistData);
        }

        return $blacklistEntry;
    }

    public function addBlacklistData(string $email, string $name, string $data): void
    {
        $blacklistData = new UserBlacklistData();
        $blacklistData->setEmail($email);
        $blacklistData->setName($name);
        $blacklistData->setData($data);
        $this->entityManager->persist($blacklistData);
    }

    public function removeEmailFromBlacklist(string $email): void
    {
        $blacklistEntry = $this->userBlacklistRepository->findOneByEmail($email);
        if ($blacklistEntry) {
            $this->entityManager->remove($blacklistEntry);
        }

        $blacklistData = $this->blacklistDataRepository->findOneByEmail($email);
        if ($blacklistData) {
            $this->entityManager->remove($blacklistData);
        }

        $subscriber = $this->subscriberRepository->findOneByEmail($email);
        if ($subscriber) {
            $subscriber->setBlacklisted(false);
        }
    }

    public function getBlacklistReason(string $email): ?string
    {
        $data = $this->blacklistDataRepository->findOneByEmail($email);
        return $data ? $data->getData() : null;
    }
}
