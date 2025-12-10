<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Model\Message;
use Psr\SimpleCache\CacheInterface;

class MessagePrecacheService
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly MessageDataLoader $messageDataLoader,
        private readonly ConfigProvider $configProvider,
    ) {
    }

    /**
     * Retrieve the base (unpersonalized) message content for a campaign from cache,
     * or cache it on first access. Legacy-like behavior: handle [URL:] token fetch
     * and basic placeholder replacements.
     */
    public function getOrCacheBaseMessageContent(Message $campaign): Message\MessageContent
    {
        $cacheKey = sprintf('messaging.message.base.%d', $campaign->getId());

        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $domain = $this->configProvider->getValue(ConfigOption::Domain);

        $loadedMessageData = ($this->messageDataLoader)($campaign);

        $content = $campaign->getContent();
        $subject = $content->getSubject();
        $html = $content->getText();
        $text = $content->getTextMessage();
        $footer = $content->getFooter();

        // If content contains a [URL:...] token, try to fetch and replace with remote content
        if (is_string($html) && preg_match('/\[URL:([^\s\]]+)\]/i', $html, $match)) {
            $remoteUrl = $match[1];
            $fetched = $this->fetchRemoteContent($remoteUrl);
            if ($fetched !== null) {
                $html = str_replace($match[0], $fetched, $html);
            }
        }

        // Replace basic placeholders [subject],[id],[fromname],[fromemail]
        $replacements = $this->buildBasicReplacements($campaign, $subject);
        $html = $this->applyReplacements($html, $replacements);
        $text = $this->applyReplacements($text, $replacements);
        $footer = $this->applyReplacements($footer, $replacements);

        $snapshot = [
            'subject' => $subject,
            'text' => $html,
            'textMessage' => $text,
            'footer' => $footer,
        ];

        $this->cache->set($cacheKey, $snapshot);

        return new Message\MessageContent($subject, $html, $text, $footer);
    }

    private function fetchRemoteContent(string $url): ?string
    {
        $ctx = stream_context_create([
            'http' => ['timeout' => 5],
            'https' => ['timeout' => 5],
        ]);

        // Ignore warnings from file_get_contents only inside this block
        set_error_handler(static function () {
            return true;
        });

        try {
            $data = file_get_contents($url, false, $ctx);
        } finally {
            restore_error_handler();
        }

        if ($data === false) {
            return null;
        }

        return $data;
    }

    private function buildBasicReplacements(Message $campaign, string $subject): array
    {
        [$fromName, $fromEmail] = $this->parseFromField($campaign->getOptions()->getFromField());
        return [
            '[subject]' => $subject,
            '[id]' => (string)($campaign->getId() ?? ''),
            '[fromname]' => $fromName,
            '[fromemail]' => $fromEmail,
        ];
    }

    private function parseFromField(string $fromField): array
    {
        $email = '';
        if (preg_match('/([^\s<>"]+@[^\s<>"]+)/', $fromField, $match)) {
            $email = str_replace(['<', '>'], '', $match[0]);
        }
        $name = trim(str_replace([$email, '"'], ['', ''], $fromField));
        $name = trim(str_replace(['<', '>'], '', $name));
        return [$name, $email];
    }

    private function applyReplacements(?string $input, array $replacements): ?string
    {
        if ($input === null) {
            return null;
        }
        return str_ireplace(array_keys($replacements), array_values($replacements), $input);
    }

    private function getFromCache(string $cacheKey): ?Message\MessageContent
    {
        $cached = $this->cache->get($cacheKey);
        if (is_array($cached)
            && array_key_exists('subject', $cached)
            && array_key_exists('text', $cached)
            && array_key_exists('textMessage', $cached)
            && array_key_exists('footer', $cached)
        ) {
            return new Message\MessageContent(
                $cached['subject'],
                $cached['text'],
                $cached['textMessage'],
                $cached['footer']
            );
        }

        return null;
    }
}
