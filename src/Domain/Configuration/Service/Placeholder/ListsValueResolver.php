<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Subscription\Repository\SubscriberListRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ListsValueResolver implements PlaceholderValueResolverInterface
{
    public function __construct(
        private readonly SubscriberListRepository $subscriberListRepository,
        private readonly TranslatorInterface $translator,
        private readonly bool $preferencePageShowPrivateLists = false,
    ) {}

    public function name(): string
    {
        return 'LISTS';
    }

    public function __invoke(PlaceholderContext $ctx): string
    {
        $names = $this->subscriberListRepository->getActiveListNamesForSubscriber(
            subscriber: $ctx->getUser(),
            showPrivate: $this->preferencePageShowPrivateLists
        );

        if ($names === []) {
            return $this->translator
                ->trans('Sorry, you are not subscribed to any of our newsletters with this email address.');
        }

        $separator = $ctx->isHtml() ? '<br/>' : "\n";

        if ($ctx->isHtml()) {
            $names = array_map(
                static fn(string $name) => htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                $names
            );
        }

        return implode($separator, $names);
    }
}
