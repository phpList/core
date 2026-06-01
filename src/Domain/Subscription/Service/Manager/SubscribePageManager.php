<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Manager;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Subscription\Model\SubscribePage;
use PhpList\Core\Domain\Subscription\Model\SubscribePageData;
use PhpList\Core\Domain\Subscription\Repository\SubscriberPageDataRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberPageRepository;
use PhpList\Core\Domain\Subscription\Service\SubscribePageConfigMigrationService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SubscribePageManager
{
    public function __construct(
        private readonly SubscriberPageRepository $pageRepository,
        private readonly SubscriberPageDataRepository $pageDataRepository,
        private readonly SubscribePageConfigMigrationService $configMigrationService,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%parallel_use_with_phplist3%')]
        private readonly bool $parallelUseWithPhpList3,
    ) {
    }

    public function findPage(int $id): ?SubscribePage
    {
        $page = $this->pageRepository->findPageWithData($id);
        if ($page === null) {
            return null;
        }

        if ($this->parallelUseWithPhpList3) {
            $changed = $this->configMigrationService->copyToPageData($page);
            if ($changed) {
                $page = $this->pageRepository->findPageWithData($id) ?? $page;
            }
        }

        return $page;
    }

    public function findPublicPage(int $id): ?SubscribePage
    {
        $page = $this->pageRepository->findPageWithData($id);
        if ($page === null) {
            return null;
        }

        if ($this->parallelUseWithPhpList3) {
            $changed = $this->configMigrationService->copyToPageData($page);
            if ($changed) {
                $page = $this->pageRepository->findPageWithData($id) ?? $page;
            }
        }

        return $page;
    }

    public function createPage(string $title, bool $active = false, ?Administrator $owner = null): SubscribePage
    {
        $page = new SubscribePage();
        $page->setTitle($title)
            ->setActive($active)
            ->setOwner($owner);

        $this->pageRepository->persist($page);

        return $page;
    }

    public function syncPageData(array $data, SubscribePage $page): void
    {
        if ($page->getId() === null) {
            throw new LogicException('Page must be persisted before syncing data');
        }
        $existingPageData = [];
        foreach ($this->getPageData($page) as $pageData) {
            $existingPageData[$pageData->getName()] = $pageData;
        }

        foreach ($data as $pageDataKey => $value) {
            if (isset($existingPageData[$pageDataKey])) {
                $pageData = $existingPageData[$pageDataKey];
                $pageData->setData($value);
                unset($existingPageData[$pageDataKey]);
                continue;
            }

            $pageData = (new SubscribePageData())
                ->setId($page->getId())
                ->setName($pageDataKey)
                ->setData($value);

            $this->pageDataRepository->persist($pageData);
        }

        foreach ($existingPageData as $pageData) {
            $this->entityManager->remove($pageData);
        }

        if ($this->parallelUseWithPhpList3) {
            $this->configMigrationService->copyToConfig(page: $page, data: $data);
        }
    }

    public function updatePage(
        SubscribePage $page,
        ?string $title = null,
        ?bool $active = null,
        ?Administrator $owner = null
    ): SubscribePage {
        if ($title !== null) {
            $page->setTitle($title);
        }
        if ($active !== null) {
            $page->setActive($active);
        }
        if ($owner !== null) {
            $page->setOwner($owner);
        }

        return $page;
    }

    public function setActive(SubscribePage $page, bool $active): void
    {
        $page->setActive($active);
    }

    public function deletePage(SubscribePage $page): void
    {
        $this->pageRepository->remove($page);
    }

    /** @return SubscribePageData[] */
    private function getPageData(SubscribePage $page): array
    {
        return $this->pageDataRepository->getByPage($page);
    }
}
