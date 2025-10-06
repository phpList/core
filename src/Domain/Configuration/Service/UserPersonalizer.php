<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeValueRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Resolver\AttributeValueResolver;

class UserPersonalizer
{
    private const PHP_SPACE = ' ';

    public function __construct(
        private readonly ConfigProvider $config,
        private readonly LegacyUrlBuilder $urlBuilder,
        private readonly SubscriberRepository $subscriberRepository,
        private readonly SubscriberAttributeValueRepository $attributesRepository,
        private readonly AttributeValueResolver $attributeValueResolver
    ) {
    }

    public function personalize(string $value, string $email): string
    {
        $user = $this->subscriberRepository->findOneByEmail($email);
        if (!$user) {
            return $value;
        }

        $resolver = new PlaceholderResolver();
        $resolver->register('EMAIL', fn() => $user->getEmail());

        $resolver->register('UNSUBSCRIBEURL', function () use ($user) {
            $base = $this->config->getValue(ConfigOption::UnsubscribeUrl) ?? '';
            return $this->urlBuilder->withUid($base, $user->getUniqueId()) . self::PHP_SPACE;
        });

        $resolver->register('CONFIRMATIONURL', function () use ($user) {
            $base = $this->config->getValue(ConfigOption::ConfirmationUrl) ?? '';
            return $this->urlBuilder->withUid($base, $user->getUniqueId()) . self::PHP_SPACE;
        });
        $resolver->register('PREFERENCESURL', function () use ($user) {
            $base = $this->config->getValue(ConfigOption::PreferencesUrl) ?? '';
            return $this->urlBuilder->withUid($base, $user->getUniqueId()) . self::PHP_SPACE;
        });

        $resolver->register(
            'SUBSCRIBEURL',
            fn() => ($this->config->getValue(ConfigOption::SubscribeUrl) ?? '') . self::PHP_SPACE
        );
        $resolver->register('DOMAIN', fn() => $this->config->getValue(ConfigOption::Domain) ?? '');
        $resolver->register('WEBSITE', fn() => $this->config->getValue(ConfigOption::Website) ?? '');

        $userAttributes = $this->attributesRepository->getForSubscriber($user);
        foreach ($userAttributes as $userAttribute) {
            $resolver->register(
                strtoupper($userAttribute->getAttributeDefinition()->getName()),
                fn() => $this->attributeValueResolver->resolve($userAttribute)
            );
        }

        $out = $resolver->resolve($value);

        return (string) $out;
    }
}
