<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;

final class SubscribeUrlValueResolver implements PlaceholderValueResolverInterface
{
    public function __construct(private readonly ConfigProvider $config)
    {
    }

    public function name(): string
    {
        return 'SUBSCRIBEURL';
    }

    public function __invoke(PlaceholderContext $ctx): string
    {
        $url = $this->config->getValue(ConfigOption::SubscribeUrl) ?? '';

        if ($ctx->isHtml()) {
            return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return $url;
    }
}
