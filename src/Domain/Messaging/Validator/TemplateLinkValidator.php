<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Validator;

use DOMDocument;
use PhpList\Core\Domain\Common\Model\ValidationContext;
use PhpList\Core\Domain\Common\Validator\ValidatorInterface;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Contracts\Translation\TranslatorInterface;

class TemplateLinkValidator implements ValidatorInterface
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    private const PLACEHOLDERS = [
        '[PREFERENCESURL]',
        '[UNSUBSCRIBEURL]',
        '[BLACKLISTURL]',
        '[FORWARDURL]',
        '[CONFIRMATIONURL]',
    ];

    public function validate(mixed $value, ValidationContext $context = null): void
    {
        if (!is_string($value) || !$context->get('checkLinks', false)) {
            return;
        }
        $links = $this->extractLinks($value);
        $invalid = [];

        foreach ($links as $link) {
            if (!preg_match('#^https?://#i', $link) &&
                !preg_match('#^mailto:#i', $link) &&
                !in_array(strtoupper($link), self::PLACEHOLDERS, true)
            ) {
                $invalid[] = $link;
            }
        }

        if (!empty($invalid)) {
            throw new ValidatorException(
                $this->translator->trans('Not full URLs: %urls%', ['%urls%' => implode(', ', $invalid)]),
            );
        }
    }

    private function extractLinks(string $html): array
    {
        $dom = new DOMDocument();
        // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        @$dom->loadHTML($html);
        $links = [];

        foreach ($dom->getElementsByTagName('a') as $node) {
            $href = $node->getAttribute('href');
            if ($href) {
                $links[] = $href;
            }
        }

        return $links;
    }
}
