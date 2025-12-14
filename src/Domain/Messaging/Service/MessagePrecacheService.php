<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Common\HtmlToText;
use PhpList\Core\Domain\Common\RemotePageFetcher;
use PhpList\Core\Domain\Common\TextParser;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Identity\Repository\AdminAttributeDefinitionRepository;
use PhpList\Core\Domain\Identity\Repository\AdministratorRepository;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\TemplateImage;
use PhpList\Core\Domain\Messaging\Repository\TemplateImageRepository;
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
        private readonly TemplateImageRepository $templateImageRepository,
        private readonly RemotePageFetcher $remotePageFetcher,
        private readonly EventLogManager $eventLogManager,
        private readonly AdminAttributeDefinitionRepository $adminAttributeDefRepository,
        private readonly AdministratorRepository $adminRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly bool $useManualTextPart,
        private readonly string $uploadImageDir,
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

        $cached[$messageId]['adminattributes'] = [];
        $ownerAttrValues = $this->adminAttributeDefRepository->getForAdmin($campaign->getOwner());
        foreach ($ownerAttrValues as $attr) {
            $cached[$messageId]['adminattributes']['OWNER.'.$attr['name']] = $attr['value'];
        }

        $relatedAdmins = $this->adminRepository->createQueryBuilder('a')
            ->select('DISTINCT a')
            ->join('a.ownedLists', 'ag')
            ->join('ag.listMessages', 'lm')
            ->join('lm.message', 'm')
            ->where('m.id = :messageId')
            ->setParameter('messageId', $messageId)
            ->getQuery()
            ->getResult();

        if (count($relatedAdmins) === 1) {
            $listOwnerAttrValues = $this->adminAttributeDefRepository->getForAdmin($relatedAdmins[0]);
        } else {
            $listOwnerAttrValues = $this->adminAttributeDefRepository->getAllWIthEmptyValues();
        }

        foreach ($listOwnerAttrValues as $attr) {
            $cached[$messageId]['adminattributes']['LISTOWNER.'.$attr['name']] = $attr['value'];
        }

        $baseurl = $this->configProvider->getValue(ConfigOption::Website);
        if ($this->uploadImageDir) {
            //# escape subdirectories, otherwise this renders empty
            $dir = str_replace('/', '\/', $this->uploadImageDir);
            $cached[$messageId]['content'] = preg_replace('/<img(.*)src="\/'.$dir.'(.*)>/iU',
                '<img\\1src="'.$GLOBALS['public_scheme'].'://'.$baseurl.'/'.$this->uploadImageDir.'\\2>',
                $cached[$messageId]['content']);
        }

        foreach (['content', 'template', 'htmlfooter'] as $element) {
            $cached[$messageId][$element] = $this->parseLogoPlaceholders($cached[$messageId][$element]);
        }

        $this->cache->set($cacheKey, $cached);

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

    private function parseLogoPlaceholders($content)
    {
        //# replace Logo placeholders
        preg_match_all('/\[LOGO\:?(\d+)?\]/', $content, $logoInstances);
        foreach ($logoInstances[0] as $index => $logoInstance) {
            $size = sprintf('%d', $logoInstances[1][$index]);
            if (!empty($size)) {
                $logoSize = $size;
            } else {
                $logoSize = '500';
            }
            $this->createCachedLogoImage($logoSize);
            $content = str_replace($logoInstance, 'ORGANISATIONLOGO'.$logoSize.'.png', $content);
        }

        return $content;
    }

    private function createCachedLogoImage($size): void
    {
        $logoImageId = $this->configProvider->getValue(ConfigOption::OrganisationLogo);
        if (empty($logoImageId)) {
            return;
        }

        $orgLogoImage = $this->templateImageRepository->findByFilename("ORGANISATIONLOGO$size.png");
        if (!empty($orgLogoImage->getData())) {
            return;
        }

        $logoImage = $this->templateImageRepository->findById((int) $logoImageId);
        $imageContent = base64_decode($logoImage->getData());
        if (empty($imageContent)) {
            //# fall back to a single pixel, so that there are no broken images
            $imageContent = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQMAAAAl21bKAAAABGdBTUEAALGPC/xhBQAAAAZQTFRF////AAAAVcLTfgAAAAF0Uk5TAEDm2GYAAAABYktHRACIBR1IAAAACXBIWXMAAAsSAAALEgHS3X78AAAAB3RJTUUH0gQCEx05cqKA8gAAAApJREFUeJxjYAAAAAIAAUivpHEAAAAASUVORK5CYII=');
        }

        $imgSize = getimagesizefromstring($imageContent);
        $sizeW = $imgSize[0];
        $sizeH = $imgSize[1];
        if ($sizeH > $sizeW) {
            $sizeFactor = (float) ($size / $sizeH);
        } else {
            $sizeFactor = (float) ($size / $sizeW);
        }
        $newWidth = (int) ($sizeW * $sizeFactor);
        $newHeight = (int) ($sizeH * $sizeFactor);

        if ($sizeFactor < 1) {
            $original = imagecreatefromstring($imageContent);
            //# creates a black image (why would you want that....)
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagesavealpha($resized, true);
            //# white. All the methods to make it transparent didn't work for me @@TODO really make transparent
            $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
            imagefill($resized, 0, 0, $transparent);

            if (imagecopyresized($resized, $original, 0, 0, 0, 0, $newWidth, $newHeight, $sizeW, $sizeH)) {
                $this->entityManager->remove($orgLogoImage);

                //# rather convoluted way to get the image contents
                $buffer = ob_get_contents();
                ob_end_clean();
                ob_start();
                imagepng($resized);
                $imageContent = ob_get_contents();
                ob_end_clean();
                echo $buffer;
            }
        }
        // else copy original
        $templateImage = (new TemplateImage())
            ->setFilename("ORGANISATIONLOGO$size.png")
            ->setMimetype($imgSize['mime'])
            ->setData(base64_encode($imageContent))
            ->setWidth($newWidth)
            ->setHeight($newHeight);

        $this->entityManager->persist($templateImage);

    }
}
