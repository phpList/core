<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PreferencesValueResolver implements PlaceholderValueResolverInterface
{
    public function __construct(
        private readonly ConfigProvider $config,
        private readonly TranslatorInterface $translator,
    ) {}

    public function name(): string
    {
        return 'PREFERENCES';
    }

    public function __invoke(PlaceholderContext $ctx): string
    {
        $url = $this->config->getValue(ConfigOption::PreferencesUrl) ?? '';
        $sep = !str_contains($url, '?') ? '?' : '&';

        if ($ctx->isHtml()) {
            $label = $this->translator->trans('This link');
            $safeLabel = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return sprintf(
                '<a href="%s%suid=%s">%s</a> ',
                $safeUrl,
                htmlspecialchars($sep),
                $ctx->getUser()->getUniqueId(),
                $safeLabel,
            );
        }

        return sprintf('%s%suid=%s', $url, $sep, $ctx->getUser()->getUniqueId());
    }
}
