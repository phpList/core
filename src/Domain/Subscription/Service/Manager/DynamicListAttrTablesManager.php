<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Manager;

use PhpList\Core\Domain\Common\Model\AttributeTypeEnum;
use PhpList\Core\Domain\Subscription\Message\DynamicTableMessage;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeDefinitionRepository;
use Symfony\Component\Messenger\MessageBusInterface;
use function Symfony\Component\String\u;

class DynamicListAttrTablesManager
{
    private string $prefix;

    public function __construct(
        private readonly SubscriberAttributeDefinitionRepository $definitionRepository,
        private readonly MessageBusInterface $messageBus,
        string $dbPrefix = 'phplist_',
        string $dynamicListTablePrefix = 'listattr_',
    ) {
        $this->prefix = $dbPrefix . $dynamicListTablePrefix;
    }

    public function resolveTableName(string $name, ?AttributeTypeEnum $type): ?string
    {
        if ($type === null) {
            return null;
        }

        if (!$type->isMultiValued()) {
            return null;
        }

        $base = u($name)->snake()->toString();
        $candidate = $base;
        $index = 1;
        while ($this->definitionRepository->existsByTableName($candidate)) {
            $suffix = $index;
            $candidate = $base . $suffix;
            $index++;
        }

        return $candidate;
    }

    public function createOptionsTableIfNotExists(string $listTable): void
    {
        $fullTableName = $this->prefix . $listTable;

        $this->messageBus->dispatch(new DynamicTableMessage($fullTableName));
    }
}
