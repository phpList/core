<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Manager;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Subscription\Model\SubscribePage;
use PhpList\Core\Domain\Subscription\Model\SubscribePageData;
use PhpList\Core\Domain\Subscription\Repository\SubscriberPageDataRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberPageRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SubscribePageManager
{
    public function __construct(
        private readonly SubscriberPageRepository $pageRepository,
        private readonly SubscriberPageDataRepository $pageDataRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function createPage(string $title, bool $active = false, ?Administrator $owner = null): SubscribePage
    {
        $page = new SubscribePage();
        $page->setTitle($title)
            ->setActive($active)
            ->setOwner($owner);

        $this->pageRepository->save($page);

        return $page;
    }

    public function getPage(int $id): SubscribePage
    {
        /** @var SubscribePage|null $page */
        $page = $this->pageRepository->find($id);
        if (!$page) {
            throw new NotFoundHttpException('Subscribe page not found');
        }

        return $page;
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

        $this->entityManager->flush();

        return $page;
    }

    public function setActive(SubscribePage $page, bool $active): void
    {
        $page->setActive($active);
        $this->entityManager->flush();
    }

    public function deletePage(SubscribePage $page): void
    {
        $this->pageRepository->remove($page);
    }

    public function getPageData(SubscribePage $page, string $name): ?string
    {
        /** @var SubscribePageData|null $data */
        $data = $this->pageDataRepository->findByPageAndName($page, $name);
        return $data?->getData();
    }

    public function setPageData(SubscribePage $page, string $name, ?string $value): SubscribePageData
    {
        /** @var SubscribePageData|null $data */
        $data = $this->pageDataRepository->findByPageAndName($page, $name);

        if (!$data) {
            $data = (new SubscribePageData())
                ->setId((int)$page->getId())
                ->setName($name);
            $this->entityManager->persist($data);
        }

        $data->setData($value);
        $this->entityManager->flush();

        return $data;
    }
}
