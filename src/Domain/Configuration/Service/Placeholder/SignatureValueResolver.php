<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SignatureValueResolver implements PlaceholderValueResolverInterface
{
    public function __construct(
        private readonly ConfigProvider $config,
        #[Autowire('%messaging.email_text_credits%')] private readonly bool $emailTextCredits = false,
    ) {
    }

    public function name(): string
    {
        return 'SIGNATURE';
    }

    public function __invoke(PlaceholderContext $ctx): string
    {
        if ($ctx->isHtml()) {
            if ($this->emailTextCredits) {
                return $this->config->getValue(ConfigOption::PoweredByText) ?? '';
            }

            return preg_replace(
                '/src=".*power-phplist.png"/',
                'src="powerphplist.png"',
                $this->config->getValue(ConfigOption::PoweredByImage) ?? ''
            );
        }

        return "\n\n-- powered by phpList, www.phplist.com --\n\n";
    }
}
