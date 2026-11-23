<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class UserTrackValueResolver implements PlaceholderValueResolverInterface
{
    public function __construct(
        #[Autowire('%app.rest_api_base_url%')] private readonly string $restApiBaseUrl,
    ) {
    }

    public function name(): string
    {
        return 'USERTRACK';
    }

    public function __invoke(PlaceholderContext $ctx): string
    {
        // The stored domain is scheme-free, which would make the pixel src relative, so always use the absolute base
        $base = rtrim($this->restApiBaseUrl, '/');

        if ($ctx->isText() || $base === '') {
            return '';
        }
        $uid = $ctx->forwardedBy() ? 'forwarded' : $ctx->getUser()->getUniqueId();

        return '<img src="'
            . $base
            . '/t/open.gif?u='
            . $uid
            . '&amp;m='
            . $ctx->messageId()
            . '" width="1" height="1" border="0" alt="" />';
    }
}
