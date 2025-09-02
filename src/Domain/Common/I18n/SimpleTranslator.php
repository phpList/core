<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\I18n;

/**
 * Minimal translator to support message keys and parameter interpolation.
 * Designed to be compatible with future integration with Symfony Translator and POEditor.
 */
class SimpleTranslator implements TranslatorInterface
{
    private string $defaultLocale;

    /** @var array<string,array<string,string>> */
    private array $catalogues = [];

    public function __construct(string $defaultLocale = 'en')
    {
        $this->defaultLocale = $defaultLocale;
    }

    public function translate(string $key, array $params = [], ?string $locale = null): string
    {
        $loc = $locale ?? $this->defaultLocale;
        $messages = $this->loadCatalogue($loc);
        $message = $messages[$key] ?? $key;

        $replacements = [];
        foreach ($params as $name => $value) {
            $replacements['{' . $name . '}'] = (string)$value;
        }

        return strtr($message, $replacements);
    }

    /**
     * @return array<string,string>
     */
    private function loadCatalogue(string $locale): array
    {
        if (!isset($this->catalogues[$locale])) {
            $pathPhp = __DIR__ . '/../../../../resources/translations/messages.' . $locale . '.php';
            if (is_file($pathPhp)) {
                /** @var array<string,string> $messages */
                $messages = include $pathPhp;
            } else {
                $fallback = __DIR__ . '/../../../../resources/translations/messages.en.php';
                if (is_file($fallback)) {
                    /** @var array<string,string> $messages */
                    $messages = include $fallback;
                } else {
                    $messages = [];
                }
            }
            $this->catalogues[$locale] = $messages;
        }
        return $this->catalogues[$locale];
    }
}
