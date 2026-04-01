<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;

class UserDataSupportingResolver implements SupportingPlaceholderResolverInterface
{
    private array $supportedKeys = [
        'CONFIRMED',
        'BLACKLISTED',
        'OPTEDIN',
        'BOUNCECOUNT',
        'ENTERED',
        'MODIFIED',
        'UNIQID',
        'UUID',
        'HTMLEMAIL',
        'SUBSCRIBEPAGE',
        'RSSFREQUENCY',
        'DISABLED',
        'FOREIGNKEY',
    ];

    public function __construct(private readonly SubscriberRepository $subscriberRepository)
    {
    }

    public function supports(string $key, PlaceholderContext $ctx): bool
    {
        return in_array(strtoupper($key), $this->supportedKeys);
    }

    public function resolve(string $key, PlaceholderContext $ctx): ?string
    {
        $canon = strtoupper($key);
        $data = $this->subscriberRepository->getDataById($ctx->getUser()->getId());

        foreach ($data as $k => $value) {
            if (strtoupper((string) $k) !== $canon) {
                continue;
            }
            if ($value === null || $value === '') {
                return null;
            }
            return is_scalar($value) ? (string) $value : null;
        }
        return null;
    }
}
