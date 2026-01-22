<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;

final class ContactUrlValueResolver implements PlaceholderValueResolverInterface
{
    public function __construct(private readonly ConfigProvider $config)
    {
    }

    public function name(): string
    {
        return 'CONTACTURL';
    }

    public function __invoke(PlaceholderContext $ctx): string
    {
        if ($ctx->isText()) {
            return $this->config->getValue(ConfigOption::VCardUrl) ?? '';
        }

        return htmlspecialchars($this->config->getValue(ConfigOption::VCardUrl) ?? '');
    }
}
