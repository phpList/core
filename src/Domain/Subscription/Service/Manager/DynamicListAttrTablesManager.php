<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Manager;

use PhpList\Core\Domain\Subscription\Message\DynamicTableMessage;
use PhpList\Core\Domain\Subscription\Model\AttributeTypeEnum;
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

    /**
     * Resolve an available snake_case table name for a multi-valued attribute.
     *
     * @param string $name The candidate name to convert to snake_case.
     * @param AttributeTypeEnum|null $type The attribute type; must indicate a multi-valued attribute to resolve a table name.
     * @return string|null The chosen table name (snake_case, without prefix) if $type indicates a multi-valued attribute; `null` if $type is null or not multi-valued.
     */
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

    /**
     * Ensure a dynamic options table exists for the given list.
     *
     * Dispatches a DynamicTableMessage with the full table name (configured prefix + provided base)
     * to request creation of the table if it does not already exist.
     *
     * @param string $listTable The base name of the list table (suffix appended to the configured prefix).
     */
    public function createOptionsTableIfNotExists(string $listTable): void
    {
        $fullTableName = $this->prefix . $listTable;

        $this->messageBus->dispatch(new DynamicTableMessage($fullTableName));
    }
}