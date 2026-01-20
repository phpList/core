<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ForwardMessageIdValueResolver implements PatternValueResolverInterface
{
    public function __construct(
        private readonly ConfigProvider $config,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function pattern(): string
    {
        return '/\[FORWARD:([^\]]+)\]/Uxm';
    }

    public function __invoke(PlaceholderContext $ctx, array $matches): string
    {
        // $matches[0] is full match: [FORWARD:...]  $matches[1] is inside: messageid[:linktext]
        $newForward = (string) $matches[1];
        $label = $this->translator->trans('This link');

        if (str_contains($newForward, ':')) {
            [$forwardMessage, $label] = explode(':', $newForward, 2);
        } else {
            $forwardMessage = $newForward;
        }

        $forwardMessage = trim($forwardMessage);
        if ($forwardMessage === '') {
            return '';
        }

        $messageId = (int) $forwardMessage;

        $url = $this->config->getValue(ConfigOption::ForwardUrl) ?? '';
        $sep = !str_contains($url, '?') ? '?' : '&';
        $uid = $ctx->getUser()->getUniqueId();

        if ($ctx->isHtml()) {
            $forwardUrl = sprintf(
                '%s%suid=%s&amp;mid=%d',
                htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                htmlspecialchars($sep, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                $uid,
                $messageId
            );

            return sprintf(
                '<a href="%s">%s</a>',
                $forwardUrl,
                htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            );
        }

        $forwardUrl = sprintf('%s%suid=%s&mid=%d', $url, $sep, $uid, $messageId);

        return $label . ' ' . $forwardUrl;
    }
}
