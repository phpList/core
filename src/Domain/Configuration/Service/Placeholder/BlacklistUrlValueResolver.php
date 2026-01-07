<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\LegacyUrlBuilder;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;

final class BlacklistUrlValueResolver implements PlaceholderValueResolverInterface
{
    public function __construct(
        private readonly ConfigProvider $config,
        private readonly LegacyUrlBuilder $urlBuilder,
    ) {}

    public function name(): string
    {
        return 'BLACKLISTURL';
    }

    public function __invoke(PlaceholderContext $ctx): string
    {
        $base = $this->config->getValue(ConfigOption::UnsubscribeUrl) ?? '';
        $url = $this->urlBuilder->withEmail($base, $ctx->getUser()->getEmail());

        if ($ctx->isHtml()) {
            return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return $url;
    }
}
