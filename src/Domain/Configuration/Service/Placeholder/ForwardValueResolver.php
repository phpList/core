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
    ) {
    }

    public function name(): string
    {
        return 'FORWARD';
    }

    public function __invoke(PlaceholderContext $ctx): string
    {
        $url = $this->config->getValue(ConfigOption::ForwardUrl);
        if (empty($url)) {
            return '';
        }
        $sep = !str_contains($url, '?') ? '?' : '&';
        $uid = $ctx->forwardedBy() ? 'forwarded' : $ctx->getUser()->getUniqueId();

        if ($ctx->isHtml()) {
            $label = $this->translator->trans('This link');

            return '<a href="'
                . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . htmlspecialchars($sep)
                . 'uid='
                . $uid
                . '&amp;mid='
                . $ctx->messageId()
                . '">'
                . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</a> ';
        }

        return sprintf('%s%suid=%s&mid=%d ', $url, $sep, $uid, $ctx->messageId());
    }
}
