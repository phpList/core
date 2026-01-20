<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SubscribeValueResolver implements PlaceholderValueResolverInterface
{
    public function __construct(
        private readonly ConfigProvider $config,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return 'SUBSCRIBE';
    }

    public function __invoke(PlaceholderContext $ctx): string
    {
        $url = $this->config->getValue(ConfigOption::SubscribeUrl) ?? '';

        if ($ctx->isHtml()) {
            $label = $this->translator->trans('This link');
            $safeLabel = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return '<a href="' . $safeUrl . '">' . $safeLabel . '</a>';
        }

        return $url;
    }
}
