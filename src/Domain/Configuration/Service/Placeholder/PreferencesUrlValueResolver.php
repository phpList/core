<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;

final class PreferencesUrlValueResolver implements PlaceholderValueResolverInterface
{
    public function __construct(private readonly ConfigProvider $config)
    {
    }

    public function name(): string
    {
        return 'PREFERENCESURL';
    }

    public function __invoke(PlaceholderContext $ctx): string
    {
        $url = $this->config->getValue(ConfigOption::PreferencesUrl);
        if (empty($url)) {
            return '';
        }
        $sep = !str_contains($url, '?') ? '?' : '&';

        if ($ctx->isHtml()) {
            return sprintf(
                '%s%suid=%s',
                htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                htmlspecialchars($sep),
                $ctx->getUser()->getUniqueId(),
            );
        }

        return sprintf('%s%suid=%s', $url, $sep, $ctx->getUser()->getUniqueId());
    }
}
