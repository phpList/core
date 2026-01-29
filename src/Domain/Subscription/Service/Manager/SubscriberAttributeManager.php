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
use PhpList\Core\Domain\Subscription\Model\Dto\DynamicListAttrDto;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeDefinitionRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeValueRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        private readonly DynamicListAttrManager $dynamicListAttrManager,
        #[Autowire('%phplist.max_avatar_size%')] private readonly int $maxAvatarSize = 100000,
    ) {
    }

    public function createOrUpdateByName(
        Subscriber $subscriber,
        string $attributeName,
        ?string $value = null
    ): SubscriberAttributeValue {
        $definition = $this->attrDefinitionRepository->findOneByName($attributeName);
        if (!$definition) {
            throw new InvalidArgumentException("Attribute definition not found for name: $attributeName");
        }

        return $this->createOrUpdate($subscriber, $definition, $value);
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
        return $this->attributeRepository->findOneBySubscriberIdAndAttributeId(
            subscriberId: $subscriberId,
            attributeDefinitionId: $attributeDefinitionId
        );
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

    // todo: double-check the logic here
    public function saveUserAttribute(Subscriber $subscriber, string $attributeName, $data, ?array $files = null): bool
    {
        if (!is_array($data)) {
            $tmp = $data;
            $attributeDefinition = $this->attrDefinitionRepository->findOneByName($attributeName);
            $data = $attributeDefinition ? $attributeDefinition->toArray() : [];
            $data['value'] = $tmp;
            $data['displayvalue'] = $tmp;
        }

        if (!empty($data['nodbsave']) || in_array($attributeName, ['emailcheck', 'passwordcheck'], true)) {
            return false;
        }

        $attributeType = $data['type'] ?? 'textline';

        if (in_array($attributeType, ['static', 'password', 'htmlpref'], true)) {
            if ($this->configProvider->getBoolValue(ConfigOption::DontSaveUserPassword) && $data['type'] === 'password') {
                $data['value'] = 'not authoritative';
            }

            switch ($attributeName) {
                case 'email':
                    $subscriber->setEmail($data['value']);
                    break;

                case 'htmlpref':
                    $subscriber->setHtmlEmail((bool)$data['value']);
                    break;

                // todo: add more cases as needed (maybe all fields from the table?)
                default:
                    throw new InvalidArgumentException(sprintf('Unknown field "%s"', $attributeName));
            }

            if ($attributeType === 'password') {
                $subscriber->setPasswordChanged(new DateTime());
                $subscriber->setPassword(hash('sha256', $data['value']));
            }

            return true;
        }

        $attributeDefinition = $this->attrDefinitionRepository->findOneByName($attributeName);
        $autoCreateAttributes = (bool) $this->configProvider->getValue(ConfigOption::AutoCreateAttributes);

        if ($attributeDefinition === null && !empty($data['name']) && $autoCreateAttributes) {
            $tableName = $this->dynamicTablesManager->resolveTableName(name: $data['name'], type: $attributeType);
            $attributeDefinition = (new SubscriberAttributeDefinition())
                ->setName($data['name'])
                ->setType($attributeType)
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

        switch ($attributeType) {
            case 'select':
                if ($tableName === null) {
                    $resolved = $this->dynamicTablesManager->resolveTableName(
                        name: $data['name'] ?? $attributeDefinition->getName(),
                        type: AttributeTypeEnum::Select,
                    );
                    if ($resolved !== null) {
                        $tableName = $resolved;
                        $attributeDefinition->setTableName($tableName);
                    }
                }
                if ($tableName !== null) {
                    $this->dynamicTablesManager->createOptionsTableIfNotExists($tableName);
                }

                $displayValue = (string)($data['displayvalue'] ?? $data['value'] ?? '');
                $optionId = null;

                // Try to find an existing option by name (case-insensitive) in hydrated options
                foreach ($attributeDefinition->getOptions() as $optDto) {
                    if (mb_strtolower($optDto->name) === mb_strtolower($displayValue)) {
                        $optionId = $optDto->id !== null ? (int)$optDto->id : null;
                        break;
                    }
                }

                // If not found and non-empty, insert it using DynamicListAttrManager
                if ($optionId === null && $displayValue !== '' && $tableName !== null) {
                    $inserted = $this->dynamicListAttrManager->insertOptions(
                        listTable: $tableName,
                        rawOptions: [new DynamicListAttrDto(id: null, name: $displayValue, listOrder: null)]
                    );
                    if (!empty($inserted) && $inserted[0]->id !== null) {
                        $optionId = (int)$inserted[0]->id;
                    }
                }

                // Persist subscriber attribute value as the option id (string) or empty
                $this->createOrUpdate(
                    subscriber: $subscriber,
                    definition: $attributeDefinition,
                    value: $optionId !== null ? (string)$optionId : null
                );

                break;
            case 'avatar':
                if (is_array($files)) {
                    // try to locate upload field
                    $attid = $attid ?? ($attributeDefinition?->getId());
                    $candidateFields = [];
                    if ($attid !== null) {
                        $candidateFields[] = 'attribute'.$attid.'_file';
                    }
                    $candidateFields[] = $attributeName.'_file';
                    $candidateFields[] = 'avatar_file';

                    $uploadField = null;
                    foreach ($candidateFields as $field) {
                        if (!empty($files[$field]['name']) && !empty($files[$field]['tmp_name'])) {
                            $uploadField = $field;
                            break;
                        }
                    }

                    if ($uploadField !== null) {
                        $tmpName = $files[$uploadField]['tmp_name'];
                        $size = @filesize($tmpName) ?: 0;
                        if ($size > 0 && $size < $this->maxAvatarSize) {
                            $avatar = file_get_contents($tmpName);
                            $encoded = base64_encode((string)$avatar);
                            $this->createOrUpdate(
                                subscriber: $subscriber,
                                definition: $attributeDefinition,
                                value: $encoded
                            );
                        }
                    }
                }
                break;
            default:
                $this->createOrUpdate(
                    subscriber: $subscriber,
                    definition: $attributeDefinition,
                    value: isset($data['value']) ? (string)$data['value'] : null
                );

                break;
        }

        return true;
    }
}
