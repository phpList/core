<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\Placeholder\PatternValueResolverInterface;
use PhpList\Core\Domain\Configuration\Service\Placeholder\SupportingPlaceholderResolverInterface;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Service\Placeholder\PlaceholderValueResolverInterface;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeValueRepository;
use PhpList\Core\Domain\Subscription\Service\Resolver\AttributeValueResolver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class MessagePlaceholderProcessor
{
    public function __construct(
        private readonly ConfigProvider $config,
        private readonly SubscriberAttributeValueRepository $attributesRepository,
        private readonly AttributeValueResolver $attributeValueResolver,
        /** @var iterable<PlaceholderValueResolverInterface> */
        private readonly iterable $placeholderResolvers,
        /** @var iterable<PatternValueResolverInterface> */
        private readonly iterable $patternResolvers,
        /** @var iterable<SupportingPlaceholderResolverInterface> */
        private readonly iterable $supportingResolvers,
        #[Autowire('%messaging.always_add_user_track%')] private readonly bool $alwaysAddUserTrack,
        #[Autowire('%phplist.keep_forwarded_attributes%')] private readonly bool $keepForwardedAttributes,
    ) {
    }

    public function process(
        string $value,
        Subscriber $receiver,
        OutputFormat $format,
        MessagePrecacheDto $messagePrecacheDto,
        ?int $campaignId = null,
        ?Subscriber $forwardedBy = null,
    ): string {
        $value = $this->ensureStandardPlaceholders($value, $format);

        $resolver = new PlaceholderResolver();
        $resolver->register('EMAIL', fn(PlaceholderContext $ctx) => $ctx->user->getEmail());
        $resolver->register('FORWARDEDBY', fn(PlaceholderContext $ctx) => $ctx->forwardedBy()->getEmail());
        $resolver->register('MESSAGEID', fn(PlaceholderContext $ctx) => $ctx->messageId());
        $resolver->register('FORWARDFORM', fn(PlaceholderContext $ctx) => '');
        $resolver->register(
            name: 'USERID',
            resolver: fn(PlaceholderContext $ctx) => $ctx->forwardedBy() ? 'forwarded' : $ctx->user->getUniqueId()
        );
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

        foreach ($this->placeholderResolvers as $placeholderResolver) {
            $resolver->register($placeholderResolver->name(), $placeholderResolver);
        }

        foreach ($this->patternResolvers as $patternResolver) {
            $resolver->registerPattern($patternResolver->pattern(), $patternResolver);
        }

        foreach ($this->supportingResolvers as $supportingResolver) {
            $resolver->registerSupporting($supportingResolver);
        }

        $userForAttributes = ($forwardedBy && $this->keepForwardedAttributes) ? $forwardedBy : $receiver;
        $userAttributes = $this->attributesRepository->getForSubscriber($userForAttributes);
        foreach ($userAttributes as $userAttribute) {
            $resolver->register(
                name: strtoupper($userAttribute->getAttributeDefinition()->getName()),
                resolver: fn(PlaceholderContext $ctx) => $this->attributeValueResolver->resolve($userAttribute)
            );
        }

        return $resolver->resolve(
            value: $value,
            context: new PlaceholderContext(
                user: $receiver,
                format: $format,
                messagePrecacheDto: $messagePrecacheDto,
                forwardedBy: $forwardedBy,
                messageId: $campaignId,
            )
        );
    }

    private function ensureStandardPlaceholders(string $value, OutputFormat $format): string
    {
        if (!str_contains($value, '[FOOTER]')) {
            $sep = $format === OutputFormat::Html ? '<br />' : "\n\n";
            $value = $this->appendContent($value, $sep . '[FOOTER]');
        }

        if (!str_contains($value, '[SIGNATURE]')) {
            $sep = $format === OutputFormat::Html ? ' ' : "\n";
            $value = $this->appendContent($value, $sep . '[SIGNATURE]');
        }

        if ($this->alwaysAddUserTrack && $format === OutputFormat::Html && !str_contains($value, '[USERTRACK]')) {
            $value = $this->appendContent($value, ' ' . '[USERTRACK]');
        }

        return $value;
    }

    private function appendContent(string $message, string $append): string
    {
        if (preg_match('#</body>#i', $message)) {
            $message = preg_replace('#</body>#i', $append . '</body>', $message);
        } else {
            $message .= $append;
        }

        return $message;
    }
}
