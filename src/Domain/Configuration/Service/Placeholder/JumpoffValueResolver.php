<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\LegacyUrlBuilder;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;

class JumpoffValueResolver implements PlaceholderValueResolverInterface
{
    public function __construct(
        private readonly ConfigProvider $config,
        private readonly LegacyUrlBuilder $urlBuilder,
    ) {
    }

    public function name(): string
    {
        return 'JUMPOFF';
    }

    public function __invoke(PlaceholderContext $ctx): string
    {
        $base = $this->config->getValue(ConfigOption::UnsubscribeUrl) ?? '';
        $url = $this->urlBuilder->withUid($base, $ctx->getUser()->getUniqueId());

        if ($ctx->isHtml()) {
            return '';
        }

        return $url . '&jo=1';
    }
}
