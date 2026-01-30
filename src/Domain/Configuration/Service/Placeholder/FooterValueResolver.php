<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;

final class FooterValueResolver implements PlaceholderValueResolverInterface
{
    public function __construct(
        private readonly ConfigProvider $config,
        private readonly bool $forwardAlternativeContent,
    ) {
    }

    public function name(): string
    {
        return 'FOOTER';
    }

    public function __invoke(PlaceholderContext $ctx): string
    {
        if ($ctx->forwardedBy() === null) {
            return $ctx->isText() ? $ctx->messagePrecacheDto->textFooter : $ctx->messagePrecacheDto->htmlFooter;
        }

        //0013076: different content when forwarding 'to a friend'
        if ($this->forwardAlternativeContent) {
            return stripslashes($ctx->messagePrecacheDto->footer);
        }

        return $this->config->getValue(ConfigOption::ForwardFooter) ?? '';
    }
}
