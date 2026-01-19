<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;

final class ConfirmationUrlValueResolver implements PlaceholderValueResolverInterface
{
    public function __construct(private readonly ConfigProvider $config)
    {
    }

    public function name(): string
    {
        return 'CONFIRMATIONURL';
    }

    public function __invoke(PlaceholderContext $ctx): string
    {
        $url = $this->config->getValue(ConfigOption::ConfirmationUrl) ?? '';
        $sep = !str_contains($url, '?') ? '?' : '&';

        if ($ctx->isHtml()) {
            return sprintf('%s%suid=%s', $url, htmlspecialchars($sep), $ctx->getUser()->getUniqueId());
        }

        return sprintf('%s%suid=%s', $url, $sep, $ctx->getUser()->getUniqueId());
    }
}
