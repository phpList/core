<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Configuration\Model\Config;
use PhpList\Core\Domain\Configuration\Repository\ConfigRepository;
use PhpList\Core\Domain\Subscription\Model\SubscribePage;
use PhpList\Core\Domain\Subscription\Model\SubscribePageData;
use PhpList\Core\Domain\Subscription\Repository\SubscriberPageDataRepository;

class SubscribePageConfigMigrationService
{
    public function __construct(
        private readonly ConfigRepository $configRepository,
        private readonly SubscriberPageDataRepository $pageDataRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    private const SUBSCRIBE_PAGE_SUFFIXES = [
        'subscribemessage',
        'subscribesubject',
        'confirmationsubject',
        'confirmationmessage',
        'unsubscribesubject',
        'unsubscribemessage',
    ];

    public function copyToPageData(SubscribePage $page): bool
    {
        $pageId = $page->getId();
        if ($pageId === null) {
            return false;
        }

        $configValues = $this->getConfigValues($pageId);
        if ($configValues === []) {
            return false;
        }

        $existingData = $this->pageDataRepository->getByPage($page);
        $existingDataByName = $this->indexPageDataByName($existingData);

        $hasChanges = $this->syncPageData(
            $pageId,
            $configValues,
            $existingData,
            $existingDataByName
        );

        $page->setData($existingData);

        if ($hasChanges) {
            $this->entityManager->flush();
        }

        return $hasChanges;
    }

    private function getConfigValues(int $pageId): array
    {
        $configValues = [];

        foreach (self::SUBSCRIBE_PAGE_SUFFIXES as $suffix) {
            $value = $this->configRepository->findValueByItem($suffix . ':' . $pageId);

            if ($value !== null) {
                $configValues[$suffix] = $value;
            }
        }

        return $configValues;
    }

    private function indexPageDataByName(array $existingData): array
    {
        $indexed = [];

        foreach ($existingData as $pageData) {
            $indexed[$pageData->getName()] = $pageData;
        }

        return $indexed;
    }

    private function syncPageData(
        int $pageId,
        array $configValues,
        array &$existingData,
        array $existingDataByName
    ): bool {
        $hasChanges = false;

        foreach ($configValues as $name => $value) {
            if (isset($existingDataByName[$name])) {
                $hasChanges = $this->updateExistingPageData(
                    $existingDataByName[$name],
                    $value
                ) || $hasChanges;

                continue;
            }

            $newPageData = (new SubscribePageData())
                ->setId($pageId)
                ->setName($name)
                ->setData($value);

            $this->pageDataRepository->persist($newPageData);

            $existingData[] = $newPageData;
            $hasChanges = true;
        }

        return $hasChanges;
    }

    private function updateExistingPageData(
        SubscribePageData $pageData,
        string $value
    ): bool {
        if ($pageData->getData() === $value) {
            return false;
        }

        $pageData->setData($value);

        return true;
    }

    public function copyToConfig(SubscribePage $page, array $data): void
    {
        $pageId = $page->getId();
        if ($pageId === null) {
            return;
        }

        foreach (self::SUBSCRIBE_PAGE_SUFFIXES as $suffix) {
            if (!array_key_exists($suffix, $data)) {
                continue;
            }

            $value = $data[$suffix];
            if (!is_string($value) && $value !== null) {
                continue;
            }

            $configKey = $suffix . ':' . $pageId;
            $config = $this->configRepository->findOneBy(['key' => $configKey]);
            if (!$config instanceof Config) {
                $config = (new Config())
                    ->setKey($configKey)
                    ->setValue($value);

                $this->configRepository->persist($config);
                continue;
            }

            $config->setValue($value);
        }
    }
}
