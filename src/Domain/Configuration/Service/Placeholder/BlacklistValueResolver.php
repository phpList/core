<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\LegacyUrlBuilder;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use Symfony\Contracts\Translation\TranslatorInterface;

final class BlacklistValueResolver implements PlaceholderValueResolverInterface
{
    public function __construct(
        private readonly ConfigProvider $config,
        private readonly LegacyUrlBuilder $urlBuilder,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return 'BLACKLIST';
    }

    public function __invoke(PlaceholderContext $ctx): string
    {
        $base = $this->config->getValue(ConfigOption::BlacklistUrl) ?? '';
        $url = $this->urlBuilder->withEmail($base, $ctx->getUser()->getEmail());

        if ($ctx->isHtml()) {
            $label = $this->translator->trans('Unsubscribe');
            $safeLabel = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return '<a href="' . $safeUrl . '">' . $safeLabel . '</a>';
        }

        return $url;
    }
}
