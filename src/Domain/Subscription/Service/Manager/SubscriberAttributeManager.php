<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Manager;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PhpList\Core\Domain\Common\Model\AttributeTypeEnum;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Subscription\Exception\SubscriberAttributeCreationException;
use PhpList\Core\Domain\Subscription\Model\Dto\ChangeSetDto;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeValue;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeDefinitionRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeValueRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

class SubscriberAttributeManager
{
    public function __construct(
        private readonly SubscriberAttributeValueRepository $attributeRepository,
        private readonly SubscriberAttributeDefinitionRepository $attrDefinitionRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly ConfigProvider $configProvider,
        private readonly DynamicListAttrTablesManager $dynamicTablesManager,
    ) {
    }

    public function createOrUpdate(
        Subscriber $subscriber,
        SubscriberAttributeDefinition $definition,
        ?string $value = null
    ): SubscriberAttributeValue {
        // todo: clarify which attributes can be created/updated
        $subscriberAttribute = $this->attributeRepository
            ->findOneBySubscriberAndAttribute($subscriber, $definition);

        if (!$subscriberAttribute) {
            $subscriberAttribute = new SubscriberAttributeValue($definition, $subscriber);
        }

        $value = $value ?? $definition->getDefaultValue();
        if ($value === null) {
            throw new SubscriberAttributeCreationException($this->translator->trans('Value is required'));
        }

        $subscriberAttribute->setValue($value);
        $this->entityManager->persist($subscriberAttribute);

        return $subscriberAttribute;
    }

    public function getSubscriberAttribute(int $subscriberId, int $attributeDefinitionId): ?SubscriberAttributeValue
    {
        return $this->attributeRepository->findOneBySubscriberIdAndAttributeId($subscriberId, $attributeDefinitionId);
    }

    public function delete(SubscriberAttributeValue $attribute): void
    {
        $this->attributeRepository->remove($attribute);
    }

    public function processAttributes(Subscriber $subscriber, array $attributeData): void
    {
        foreach ($attributeData as $key => $value) {
            $lowerKey = strtolower((string)$key);
            if (in_array($lowerKey, ChangeSetDto::IGNORED_ATTRIBUTES, true)) {
                continue;
            }

            $attributeDefinition = $this->attrDefinitionRepository->findOneByName($key);
            if ($attributeDefinition !== null) {
                $this->createOrUpdate(
                    subscriber: $subscriber,
                    definition: $attributeDefinition,
                    value: $value
                );
            }
        }
    }

    public function saveUserAttribute(Subscriber $subscriber, string $attributeName, $data): bool
    {
        if (!is_array($data)) {
            $tmp = $data;
            $def = $this->attrDefinitionRepository->findOneByName($attributeName);
            $data = $def ? $def->toArray() : [];
            $data['value'] = $tmp;
            $data['displayvalue'] = $tmp;
        }

        if ($data['nodbsave'] || in_array($attributeName, ['emailcheck', 'passwordcheck'], true)) {
            return false;
        }

        if (!isset($data['type'])) {
            $data['type'] = 'textline';
        }

        if (in_array($data['type'], ['static', 'password', 'htmlpref'], true)) {
            if (!empty($this->configProvider->getValue(ConfigOption::DontSaveUserPassword)) && $data['type'] === 'password') {
                $data['value'] = 'not authoritative';
            }

            switch ($attributeName) {
                case 'email':
                    $subscriber->setEmail($data['value']);
                    break;

                case 'htmlemail':
                    $subscriber->setHtmlEmail((bool)$data['value']);
                    break;

                // todo: add more cases as needed (maybe all fields from the table?)
                default:
                    throw new InvalidArgumentException(sprintf('Unknown field "%s"', $attributeName));
            }

            if ($data['type'] === 'password') {
                $subscriber->setPasswordChanged(new DateTime());
                $subscriber->setPassword(hash('sha256', $data['value']));
            }

            return true;
        }

        $attributeType = $data['type'];
        $attributeDefinition = $this->attrDefinitionRepository->findOneByName($attributeName);
        $autoCreateAttributes = (bool) $this->configProvider->getValue(ConfigOption::AutoCreateAttributes);

        if ($attributeDefinition === null && !empty($data['name']) && $autoCreateAttributes) {
            $tableName = $this->dynamicTablesManager->resolveTableName(name: $data['name'], type: $data['type']);
            $attributeDefinition = (new SubscriberAttributeDefinition())
                ->setName($data['name'])
                ->setType($data['type'])
                ->setTableName($tableName);
            $this->attrDefinitionRepository->persist($attributeDefinition);
        } else {
            $attid = $attributeDefinition->getId();
            if (empty($attributeType)) {
                $attributeType = $attributeDefinition->getType();
            }
            $tableName = $attributeDefinition->getTableName();
        }

        if ($tableName === null && !empty($data['name'])) {
            $tableName = $this->dynamicTablesManager->resolveTableName(
                name: $data['name'],
                type: AttributeTypeEnum::Checkbox,
            );
            // fix attribute without tablename
            $attributeDefinition->setTableName($tableName);
        }

    }
}
