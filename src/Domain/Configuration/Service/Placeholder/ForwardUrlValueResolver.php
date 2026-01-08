<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;

final class ForwardUrlValueResolver implements PlaceholderValueResolverInterface
{
    public function __construct(private readonly ConfigProvider $config)
    {
    }

    public function name(): string
    {
        return 'FORWARDURL';
    }

    public function __invoke(PlaceholderContext $ctx): string
    {
        $url = $this->config->getValue(ConfigOption::ForwardUrl) ?? '';
        $sep = !str_contains($url, '?') ? '?' : '&';

        if ($ctx->isHtml()) {
            return sprintf(
                '%s%suid=%s&amp;mid=%d',
                htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                htmlspecialchars($sep),
                $ctx->getUser()->getUniqueId(),
                $ctx->messageId(),
            );
        }

        return sprintf('%s%suid=%s&mid=%d ', $url, $sep, $ctx->getUser()->getUniqueId(), $ctx->messageId());
    }
}
