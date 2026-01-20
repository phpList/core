<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ContactValueResolver implements PatternValueResolverInterface
{
    public function __construct(
        private readonly ConfigProvider $config,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function pattern(): string
    {
        return '/\[CONTACT\:?(\d+)?\]/i';
    }

    public function __invoke(PlaceholderContext $ctx, array $matches): string
    {
        $url = (string) $this->config->getValue(ConfigOption::VCardUrl);
        $label = $this->translator->trans('Add us to your address book');

        if ($ctx->isHtml()) {
            $href = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $text = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return sprintf('<a href="%s">%s</a>', $href, $text);
        }

        return $label !== '' ? ($label . ': ' . $url) : $url;
    }
}
