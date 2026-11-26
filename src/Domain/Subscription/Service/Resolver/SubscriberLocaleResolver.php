<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Resolver;

use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberPageDataRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Resolves the locale used for emails sent to a subscriber.
 *
 * phplist3 has no language per subscriber or list. The language is a setting of the subscribe page
 * (`language_file` in subscribepage_data, e.g. "german.inc"), and the subscriber keeps the page they used in
 * `subscribepage`. This resolver maps that legacy language file to an ISO locale and falls back to the default.
 */
class SubscriberLocaleResolver
{
    private const LANGUAGE_FILE_KEY = 'language_file';

    /** Legacy phplist3 language file (without ".inc") => locale. */
    private const LANGUAGE_FILE_LOCALES = [
        'afrikaans' => 'af',
        'albanian' => 'sq',
        'arabic' => 'ar',
        'belgianflemish' => 'nl_BE',
        'bosnian' => 'bs',
        'bulgarian' => 'bg',
        'catalan' => 'ca',
        'croatian' => 'hr',
        'czech' => 'cs',
        'danish' => 'da',
        'dutch' => 'nl',
        'english' => 'en',
        'english-gaelic' => 'ga',
        'english-usa' => 'en_US',
        'usa' => 'en_US',
        'estonian' => 'et',
        'finnish' => 'fi',
        'french' => 'fr',
        'german' => 'de',
        'greek' => 'el',
        'hebrew' => 'he',
        'hungarian' => 'hu',
        'indonesian' => 'id',
        'italian' => 'it',
        'japanese' => 'ja',
        'latinamerican' => 'es_419',
        'norwegian' => 'nb',
        'persian' => 'fa',
        'polish' => 'pl',
        'portuguese' => 'pt_BR',
        'portuguese_pt' => 'pt_PT',
        'romanian' => 'ro',
        'russian' => 'ru',
        'serbian' => 'sr',
        'slovenian' => 'sl',
        'spanish' => 'es',
        'swedish' => 'sv',
        'swissgerman' => 'de_CH',
        'tchinese' => 'zh_TW',
        'turkish' => 'tr',
        'ukrainian' => 'uk',
        'vietnamese' => 'vi',
    ];

    public function __construct(
        private readonly SubscriberPageDataRepository $pageDataRepository,
        #[Autowire('%locale%')] private readonly string $defaultLocale,
    ) {
    }

    public function resolve(Subscriber $subscriber): string
    {
        $pageId = $subscriber->getSubscribePage();
        if ($pageId === null) {
            return $this->defaultLocale;
        }

        $data = $this->pageDataRepository->findOneBy(['id' => $pageId, 'name' => self::LANGUAGE_FILE_KEY]);

        return $this->localeFromLanguageFile($data?->getData()) ?? $this->defaultLocale;
    }

    public function localeFromLanguageFile(?string $languageFile): ?string
    {
        if ($languageFile === null || trim($languageFile) === '') {
            return null;
        }

        $key = preg_replace('/\.inc$/i', '', strtolower(basename(trim($languageFile)))) ?? '';

        return self::LANGUAGE_FILE_LOCALES[$key] ?? null;
    }
}
