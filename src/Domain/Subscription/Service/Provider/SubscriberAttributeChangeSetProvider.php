<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Provider;

use PhpList\Core\Domain\Subscription\Model\Dto\ChangeSetDto;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeValueRepository;
use PhpList\Core\Domain\Subscription\Service\Resolver\AttributeValueResolver;

class SubscriberAttributeChangeSetProvider
{
    public function __construct(
        private readonly AttributeValueResolver $resolver,
        private readonly SubscriberAttributeValueRepository $attributesRepository,
    ) {
    }

    /**
     * Get the changes between the subscriber’s existing attributes and new attribute data.
     *
     * @param Subscriber $subscriber
     * @param array<string, mixed> $attributeData
     * @return ChangeSetDto
     */
    public function getAttributeChangeSet(Subscriber $subscriber, array $attributeData): ChangeSetDto
    {
        $oldMap = $this->getMappedValues($subscriber);

        $canon = static function (array $attributes): array {
            $out = [];
            foreach ($attributes as $key => $value) {
                $out[mb_strtolower((string)$key)] = $value;
            }
            return $out;
        };

        $oldC = $canon($oldMap);
        $newC = $canon($attributeData);

        foreach (ChangeSetDto::IGNORED_ATTRIBUTES as $ignoredAttribute) {
            $lowerCaseIgnoredAttribute = mb_strtolower($ignoredAttribute);
            unset($oldC[$lowerCaseIgnoredAttribute], $newC[$lowerCaseIgnoredAttribute]);
        }

        $keys = array_values(array_unique(array_merge(array_keys($oldC), array_keys($newC))));

        $changeSet = [];
        foreach ($keys as $key) {
            $hasOld = array_key_exists($key, $oldC);
            $hasNew = array_key_exists($key, $newC);

            if ($hasOld && !$hasNew) {
                $changeSet[$key] = [$oldC[$key], null];
                continue;
            }

            if (!$hasOld && $hasNew) {
                $changeSet[$key] = [null, $newC[$key]];
                continue;
            }

            if ($oldC[$key] !== $newC[$key]) {
                $changeSet[$key] = [$oldC[$key], $newC[$key]];
            }
        }

        return new ChangeSetDto($changeSet);
    }

    private function getMappedValues(Subscriber $subscriber): array
    {
        $userAttributes = $this->attributesRepository->getForSubscriber($subscriber);
        foreach ($userAttributes as $userAttribute) {
            $data[$userAttribute->getAttributeDefinition()->getName()] = $this->resolver->resolve($userAttribute);
        }

        return $data ?? [];
    }
}
