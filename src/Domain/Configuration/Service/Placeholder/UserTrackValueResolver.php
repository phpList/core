<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class UserTrackValueResolver implements PlaceholderValueResolverInterface
{
    public function __construct(
        private readonly ConfigProvider $config,
        #[Autowire('%rest_api_domain%')] private readonly string $restApiDomain,
    ) {
    }

    public function name(): string
    {
        return 'USERTRACK';
    }

    public function __invoke(PlaceholderContext $ctx): string
    {
        $base = $this->config->getValue(ConfigOption::Domain) ?? $this->restApiDomain;

        if ($ctx->isText() || empty($base)) {
            return '';
        }
        $uid = $ctx->forwardedBy() ? 'forwarded' : $ctx->getUser()->getUniqueId();

        return '<img src="'
            . $base
            . '/ut.php?u='
            . $uid
            . '&amp;m='
            . $ctx->messageId()
            . '" width="1" height="1" border="0" alt="" />';
    }
}
