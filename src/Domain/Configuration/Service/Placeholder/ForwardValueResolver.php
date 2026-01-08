<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ForwardValueResolver implements PlaceholderValueResolverInterface
{
    public function __construct(
        private readonly ConfigProvider $config,
        private readonly TranslatorInterface $translator,
    ) {}

    public function name(): string
    {
        return 'FORWARD';
    }

    public function __invoke(PlaceholderContext $ctx): string
    {
        $url = $this->config->getValue(ConfigOption::ForwardUrl) ?? '';
        $sep = !str_contains($url, '?') ? '?' : '&';

        if ($ctx->isHtml()) {
            $label = $this->translator->trans('This link');
            $safeLabel = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return sprintf(
                '<a href="%s%suid=%s&amp;mid=%d">%s</a> ',
                $safeUrl,
                htmlspecialchars($sep),
                $ctx->getUser()->getUniqueId(),
                $ctx->messageId(),
                $safeLabel
            );
        }

        return sprintf('%s%suid=%s&mid=%d ', $url, $sep, $ctx->getUser()->getUniqueId(), $ctx->messageId());
    }
}
