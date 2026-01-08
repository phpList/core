<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Service\Placeholder\PlaceholderValueResolverInterface;
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
        /** @var iterable<PlaceholderValueResolverInterface> */
        private readonly iterable $placeholderResolvers,
    ) {
    }

    public function personalize(
        string $value,
        string $email,
        OutputFormat $format,
        ?int $messageId = null,
        ?string $forwardedBy = null,
    ): string {
        $user = $this->subscriberRepository->findOneByEmail($email);
        if (!$user) {
            return $value;
        }

        $resolver = new PlaceholderResolver();
        $resolver->register('EMAIL', fn(PlaceholderContext $ctx) => $ctx->user->getEmail());
        $resolver->register('FORWARDEDBY', fn(PlaceholderContext $ctx) => $ctx->forwardedBy());
        $resolver->register('MESSAGEID', fn(PlaceholderContext $ctx) => $ctx->messageId());
        $resolver->register('FORWARDFORM', fn(PlaceholderContext $ctx) => '');
        $resolver->register(
            name: 'SIGNATURE',
            resolver: fn(PlaceholderContext $ctx) => "\n\n-- powered by phpList, www.phplist.com --\n\n"
        );
        $resolver->register('USERID', fn(PlaceholderContext $ctx) => $ctx->user->getUniqueId());
        $resolver->register(
            name: 'WEBSITE',
            resolver: fn(PlaceholderContext $ctx) => $this->config->getValue(ConfigOption::Website) ?? ''
        );
        $resolver->register(
            name: 'DOMAIN',
            resolver: fn(PlaceholderContext $ctx) => $this->config->getValue(ConfigOption::Domain) ?? ''
        );
        $resolver->register(
            name: 'ORGANIZATION_NAME',
            resolver: fn(PlaceholderContext $ctx) => $this->config->getValue(ConfigOption::OrganisationName) ?? ''
        );
        $resolver->register(
            name: 'CONTACTURL',
            resolver: fn(PlaceholderContext $ctx) => htmlspecialchars(
                $this->config->getValue(ConfigOption::VCardUrl) ?? ''
            )
        );

        foreach ($this->placeholderResolvers as $placeholderResolver) {
            $resolver->register($placeholderResolver->name(), $placeholderResolver);
        }

        $userAttributes = $this->attributesRepository->getForSubscriber($user);
        foreach ($userAttributes as $userAttribute) {
            $resolver->register(
                name: strtoupper($userAttribute->getAttributeDefinition()->getName()),
                resolver: fn(PlaceholderContext $ctx) => $this->attributeValueResolver->resolve($userAttribute)
            );
        }

        return $resolver->resolve(
            value: $value,
            context: new PlaceholderContext(user: $user, format: $format, forwardedBy: $forwardedBy, messageId: $messageId)
        );
    }
}
