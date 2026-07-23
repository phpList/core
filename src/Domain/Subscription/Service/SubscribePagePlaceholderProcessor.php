<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Subscription\Model\SubscribePage;
use PhpList\Core\Domain\Subscription\Model\SubscribePageData;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;
use PhpList\Core\Domain\Subscription\Repository\SubscriberListRepository;

class SubscribePagePlaceholderProcessor
{
    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly SubscriberListRepository $subscriberListRepository,
    ) {
    }

    public function process(SubscribePage $page): void
    {
        $replacements = array_reduce(
            $page->getData(),
            function (array $carry, SubscribePageData $item) {
                $carry[$item->getName()] = $item->getData();
                return $carry;
            },
            []
        );
        $replacements = $this->buildReplacements($replacements, $page->getId());
        foreach ($page->getData() as $pageData) {
            if ($pageData->getData() !== null) {
                $pageData->setData(strtr($pageData->getData(), $replacements));
            }
        }
    }

    /** @return array<string, string> */
    private function buildReplacements(array $data, int $pageId): array
    {
        return [
            '[ORGANISATION_NAME]' => $this->configProvider->getValue(ConfigOption::OrganisationName) ?? '',
            '[SUBSCRIBEURL]' => $this->configProvider->getValue(ConfigOption::SubscribeUrl) . '&id=' . $pageId,
            '[UNSUBSCRIBEURL]' => $this->configProvider->getValue(ConfigOption::UnsubscribeUrl) . '&id=' . $pageId,
            '[PREFERENCESURL]' => $this->configProvider->getValue(ConfigOption::PreferencesUrl) ?? '',
            '[LISTS]' => $this->resolveListNames($data['lists'] ?? ''),
        ];
    }

    private function resolveListNames(string $listIds): string
    {
        $listIds = array_filter(array_map('trim', explode(',', $listIds)), 'is_numeric');
        $lists = $this->subscriberListRepository->getPublicByIds($listIds);

        return implode("\n", array_map(
            static fn(SubscriberList $list) => $list->getName(),
            $lists
        ));
    }
}
