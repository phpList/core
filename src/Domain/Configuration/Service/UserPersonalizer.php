<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Service\Placeholder\PlaceholderValueResolverInterface;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeValueRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberListRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Resolver\AttributeValueResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserPersonalizer
{
    private const PHP_SPACE = ' ';

    public function __construct(
        private readonly ConfigProvider $config,
        private readonly LegacyUrlBuilder $urlBuilder,
        private readonly SubscriberRepository $subscriberRepository,
        private readonly SubscriberAttributeValueRepository $attributesRepository,
        private readonly AttributeValueResolver $attributeValueResolver,
        private readonly SubscriberListRepository $subscriberListRepository,
        private readonly TranslatorInterface $translator,
        /** @var iterable<PlaceholderValueResolverInterface> */
        private readonly iterable $placeholderResolvers,
        private readonly bool $preferencePageShowPrivateLists = false
    ) {
    }

    public function personalize(string $value, string $email, OutputFormat $format): string
    {
        $user = $this->subscriberRepository->findOneByEmail($email);
        if (!$user) {
            return $value;
        }

        $resolver = new PlaceholderResolver();
        $resolver->register('EMAIL', fn(PlaceholderContext $ctx) => $ctx->subscriber->getEmail());

        foreach ($this->placeholderResolvers as $placeholderResolver) {
            $resolver->register($placeholderResolver->name(), $placeholderResolver);
        }

        $resolver->register('CONFIRMATIONURL', function () use ($user, $format) {
            $base = $this->config->getValue(ConfigOption::ConfirmationUrl) ?? '';
            $url = $this->urlBuilder->withUid($base, $user->getUniqueId());

            if ($format === OutputFormat::Html) {
                $label = $this->translator->trans('Confirm');
                $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $safeLabel = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                return '<a href="' . $safeUrl . '">' . $safeLabel . '</a>' . self::PHP_SPACE;
            }

            return $url . self::PHP_SPACE;
        });

        $resolver->register('PREFERENCESURL', function () use ($user, $format) {
            $base = $this->config->getValue(ConfigOption::PreferencesUrl) ?? '';
            $url = $this->urlBuilder->withUid($base, $user->getUniqueId());

            if ($format === OutputFormat::Html) {
                $label = $this->translator->trans('Update preferences');
                $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $safeLabel = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                return '<a href="' . $safeUrl . '">' . $safeLabel . '</a>' . self::PHP_SPACE;
            }

            return $url . self::PHP_SPACE;
        });

        $resolver->register(
            'SUBSCRIBEURL',
            fn() => ($this->config->getValue(ConfigOption::SubscribeUrl) ?? '') . self::PHP_SPACE
        );
        $resolver->register('DOMAIN', fn() => $this->config->getValue(ConfigOption::Domain) ?? '');
        $resolver->register('WEBSITE', fn() => $this->config->getValue(ConfigOption::Website) ?? '');

        // need in PersonalizedContentConstructor
        $resolver->register('LISTS', function () use ($user, $format) {
            $names = $this->subscriberListRepository->getActiveListNamesForSubscriber(
                subscriber: $user,
                showPrivate: $this->preferencePageShowPrivateLists
            );

            if ($names === []) {
                return $this->translator
                    ->trans('Sorry, you are not subscribed to any of our newsletters with this email address.');
            }

            $separator = $format === OutputFormat::Html ? '<br/>' : "\n";

            if ($format === OutputFormat::Html) {
                $names = array_map(
                    static fn(string $name) => htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    $names
                );
            }

            return implode($separator, $names);
        });

        $userAttributes = $this->attributesRepository->getForSubscriber($user);
        foreach ($userAttributes as $userAttribute) {
            $resolver->register(
                strtoupper($userAttribute->getAttributeDefinition()->getName()),
                fn() => $this->attributeValueResolver->resolve($userAttribute)
            );
        }

        return $resolver->resolve($value, new PlaceholderContext($user, $format));
    }
}
