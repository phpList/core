<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Common\HtmlToText;
use PhpList\Core\Domain\Common\RemotePageFetcher;
use PhpList\Core\Domain\Common\TextParser;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Repository\TemplateRepository;
use Psr\SimpleCache\CacheInterface;

class MessagePrecacheService
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly MessageDataLoader $messageDataLoader,
        private readonly ConfigProvider $configProvider,
        private readonly HtmlToText $htmlToText,
        private readonly TextParser $textParser,
        private readonly TemplateRepository $templateRepository,
        private readonly RemotePageFetcher $remotePageFetcher,
        private readonly EventLogManager $eventLogManager,
        private readonly bool $useManualTextPart,
    ) {
    }

    /**
     * Retrieve the base (unpersonalized) message content for a campaign from cache,
     * or cache it on first access. Legacy-like behavior: handle [URL:] token fetch
     * and basic placeholder replacements.
     */
    public function getOrCacheBaseMessageContent(Message $campaign, ?bool $forwardContent = false): ?Message\MessageContent
    {
        $cacheKey = sprintf('messaging.message.base.%d', $campaign->getId());

        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        $domain = $this->configProvider->getValue(ConfigOption::Domain);
        $messageId = $campaign->getId();

        $loadedMessageData = ($this->messageDataLoader)($campaign);

        // parse the reply-to field into its components - email and name
        if (preg_match('/([^ ]+@[^ ]+)/', $loadedMessageData['replyto'], $regs)) {
            // if there is an email in the from, rewrite it as "name <email>"
            $loadedMessageData['replyto'] = str_replace($regs[0], '', $loadedMessageData['replyto']);
            $cached[$messageId]['replytoemail'] = $regs[0];
            // if the email has < and > take them out here
            $cached[$messageId]['replytoemail'] = str_replace('<', '', $cached[$messageId]['replytoemail']);
            $cached[$messageId]['replytoemail'] = str_replace('>', '', $cached[$messageId]['replytoemail']);
            // make sure there are no quotes around the name
            $cached[$messageId]['replytoname'] = str_replace('"', '', ltrim(rtrim($loadedMessageData['replyto'])));
        } elseif (strpos($loadedMessageData['replyto'], ' ')) {
            // if there is a space, we need to add the email
            $cached[$messageId]['replytoname'] = $loadedMessageData['replyto'];
            $cached[$messageId]['replytoemail'] = "listmaster@$domain";
        } else {
            if (!empty($loadedMessageData['replyto'])) {
                $cached[$messageId]['replytoemail'] = $loadedMessageData['replyto']."@$domain";

                //# makes more sense not to add the domain to the word, but the help says it does
                //# so let's keep it for now
                $cached[$messageId]['replytoname'] = $loadedMessageData['replyto']."@$domain";
            }
        }

        $cached[$messageId]['fromname'] = $loadedMessageData['fromname'];
        $cached[$messageId]['fromemail'] = $loadedMessageData['fromemail'];
        $cached[$messageId]['to'] = $loadedMessageData['tofield'];
        //0013076: different content when forwarding 'to a friend'
        $cached[$messageId]['subject'] = $forwardContent ? stripslashes($loadedMessageData['forwardsubject']) : $loadedMessageData['subject'];
        //0013076: different content when forwarding 'to a friend'
        $cached[$messageId]['content'] = $forwardContent ? stripslashes($loadedMessageData['forwardmessage']) : $loadedMessageData['message'];
        if ($this->useManualTextPart && !$forwardContent) {
            $cached[$messageId]['textcontent'] = $loadedMessageData['textmessage'];
        } else {
            $cached[$messageId]['textcontent'] = '';
        }
        //0013076: different content when forwarding 'to a friend'
        $cached[$messageId]['footer'] = $forwardContent ? stripslashes($loadedMessageData['forwardfooter']) : $loadedMessageData['footer'];

        if (strip_tags($cached[$messageId]['footer']) != $cached[$messageId]['footer']) {
            $cached[$messageId]['textfooter'] = ($this->htmlToText)($cached[$messageId]['footer']);
            $cached[$messageId]['htmlfooter'] = $cached[$messageId]['footer'];
        } else {
            $cached[$messageId]['textfooter'] = $cached[$messageId]['footer'];
            $cached[$messageId]['htmlfooter'] = ($this->textParser)($cached[$messageId]['footer']);
        }

        $cached[$messageId]['htmlformatted'] = strip_tags($cached[$messageId]['content']) != $cached[$messageId]['content'];
        $cached[$messageId]['sendformat'] = $loadedMessageData['sendformat'];

        $cached[$messageId]['template'] = '';
        $cached[$messageId]['template_text'] = '';
        $cached[$messageId]['templateid'] = 0;
        if ($loadedMessageData['template']) {
            $template = $this->templateRepository->findOneById($loadedMessageData['template']);
            if ($template) {
                $cached[$messageId]['template'] = stripslashes($template->getContent());
                $cached[$messageId]['template_text'] = stripslashes($template->getText());
                $cached[$messageId]['templateid'] = $template->getId();
            }
        }

        //# @@ put this here, so it can become editable per email sent out at a later stage
        $cached[$messageId]['html_charset'] = 'UTF-8'; //getConfig("html_charset");
        $cached[$messageId]['text_charset'] = 'UTF-8'; //getConfig("text_charset");

        //# if we are sending a URL that contains user attributes, we cannot pre-parse the message here
        //# but that has quite some impact on speed. So check if that's the case and apply
        $cached[$messageId]['userspecific_url'] = preg_match('/\[.+\]/', $loadedMessageData['sendurl']);

        if (!$cached[$messageId]['userspecific_url']) {
            //# Fetch external content here, because URL does not contain placeholders
            if (preg_match("/\[URL:([^\s]+)\]/i", $cached[$messageId]['content'], $regs)) {
                $remoteContent = ($this->remotePageFetcher)($regs[1], []);

                if ($remoteContent) {
                    $cached[$messageId]['content'] = str_replace($regs[0], $remoteContent, $cached[$messageId]['content']);
                    $cached[$messageId]['htmlformatted'] = strip_tags($remoteContent) != $remoteContent;

                    //# 17086 - disregard any template settings when we have a valid remote URL
                    $cached[$messageId]['template'] = null;
                    $cached[$messageId]['template_text'] = null;
                    $cached[$messageId]['templateid'] = null;
                } else {
                    $this->eventLogManager->log(
                        page: 'unknown page',
                        entry: 'Error fetching URL: '.$loadedMessageData['sendurl'].' cannot proceed',
                    );

                    return null;
                }
            }
        }

        $cached[$messageId]['google_track'] = $loadedMessageData['google_track'];

        foreach (['subject', 'id', 'fromname', 'fromemail'] as $key) {
            $val = $loadedMessageData[$key];
            // Replace in content except for user-specific URL
            if (!$cached[$messageId]['userspecific_url']) {
                $cached[$messageId]['content'] = str_ireplace("[$key]", $val, $cached[$messageId]['content']);
            }
            $cached[$messageId]['textcontent'] = str_ireplace("[$key]", $val, $cached[$messageId]['textcontent']);
            $cached[$messageId]['textfooter'] = str_ireplace("[$key]", $val, $cached[$messageId]['textfooter']);
            $cached[$messageId]['htmlfooter'] = str_ireplace("[$key]", $val, $cached[$messageId]['htmlfooter']);
        }
        /*
         *  cache message owner and list owner attribute values
         */
        $cached[$messageId]['adminattributes'] = [];
        $result = Sql_Query(
            "SELECT a.name, aa.value
        FROM {$tables['adminattribute']} a
        LEFT JOIN {$tables['admin_attribute']} aa ON a.id = aa.adminattributeid AND aa.adminid = (
            SELECT owner
            FROM {$tables['message']}
            WHERE id = $messageId
        )"
        );

        if ($result !== false) {
            while ($att = Sql_Fetch_Array($result)) {
                $cached[$messageId]['adminattributes']['OWNER.'.$att['name']] = $att['value'];
            }
        }

        $result = Sql_Query(
            "SELECT DISTINCT l.owner
        FROM {$tables['list']} AS l
        JOIN  {$tables['listmessage']} AS lm ON lm.listid = l.id
        WHERE lm.messageid = $messageId"
        );

        if ($result !== false && Sql_Num_Rows($result) == 1) {
            $row = Sql_Fetch_Assoc($result);
            $listOwner = $row['owner'];
            $att_req = Sql_Query(
                "SELECT a.name, aa.value
            FROM {$tables['adminattribute']} a
            LEFT JOIN {$tables['admin_attribute']} aa ON a.id = aa.adminattributeid AND aa.adminid = $listOwner"
            );
        } else {
            $att_req = Sql_Query(
                "SELECT a.name, '' AS value
            FROM {$tables['adminattribute']} a"
            );
        }

        while ($att = Sql_Fetch_Array($att_req)) {
            $cached[$messageId]['adminattributes']['LISTOWNER.'.$att['name']] = $att['value'];
        }

        $baseurl = $GLOBALS['website'];
        if (defined('UPLOADIMAGES_DIR') && UPLOADIMAGES_DIR) {
            //# escape subdirectories, otherwise this renders empty
            $dir = str_replace('/', '\/', UPLOADIMAGES_DIR);
            $cached[$messageId]['content'] = preg_replace('/<img(.*)src="\/'.$dir.'(.*)>/iU',
                '<img\\1src="'.$GLOBALS['public_scheme'].'://'.$baseurl.'/'.UPLOADIMAGES_DIR.'\\2>',
                $cached[$messageId]['content']);
        }

        foreach (array('content', 'template', 'htmlfooter') as $element) {
            $cached[$messageId][$element] = parseLogoPlaceholders($cached[$messageId][$element]);
        }

        return 1;

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
