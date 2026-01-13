<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\Placeholder\ConfirmationUrlValueResolver;
use PhpList\Core\Domain\Configuration\Service\Placeholder\PreferencesUrlValueResolver;
use PhpList\Core\Domain\Configuration\Service\Placeholder\SubscribeUrlValueResolver;
use PhpList\Core\Domain\Configuration\Service\Placeholder\UnsubscribeUrlValueResolver;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeValueRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Resolver\AttributeValueResolver;

class UserPersonalizer
{
    public function __construct(
        private readonly ConfigProvider $config,
        private readonly SubscriberRepository $subscriberRepository,
        private readonly SubscriberAttributeValueRepository $attributesRepository,
        private readonly AttributeValueResolver $attributeValueResolver,
        private readonly UnsubscribeUrlValueResolver $unsubscribeUrlValueResolver,
        private readonly ConfirmationUrlValueResolver $confirmationUrlValueResolver,
        private readonly PreferencesUrlValueResolver $preferencesUrlValueResolver,
        private readonly SubscribeUrlValueResolver $subscribeUrlValueResolver,
    ) {
    }

    public function personalize(string $value, string $email, OutputFormat $format): string
    {
        $user = $this->subscriberRepository->findOneByEmail($email);
        if (!$user) {
            return $value;
        }

        $resolver = new PlaceholderResolver();
        $resolver->register('EMAIL', fn(PlaceholderContext $ctx) => $ctx->user->getEmail());
        $resolver->register($this->unsubscribeUrlValueResolver->name(), $this->unsubscribeUrlValueResolver);
        $resolver->register($this->confirmationUrlValueResolver->name(), $this->confirmationUrlValueResolver);
        $resolver->register($this->preferencesUrlValueResolver->name(), $this->preferencesUrlValueResolver);
        $resolver->register($this->subscribeUrlValueResolver->name(), $this->subscribeUrlValueResolver);
        $resolver->register('DOMAIN', fn(PlaceholderContext $ctx) => $this->config->getValue(ConfigOption::Domain) ?? '');
        $resolver->register('WEBSITE', fn(PlaceholderContext $ctx) => $this->config->getValue(ConfigOption::Website) ?? '');

        $userAttributes = $this->attributesRepository->getForSubscriber($user);
        foreach ($userAttributes as $userAttribute) {
            $resolver->register(
                strtoupper($userAttribute->getAttributeDefinition()->getName()),
                fn(PlaceholderContext $ctx) => $this->attributeValueResolver->resolve($userAttribute)
            );
        }

        return $resolver->resolve(
            value: $value,
            context: new PlaceholderContext(user: $user, format: $format)
        );
    }
}
