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
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
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
        private readonly string $publicSchema,
    ) {
    }

    /**
     * Retrieve the base (unpersonalized) message content for a campaign from cache,
     * or cache it on first access. Handle [URL:] token fetch and basic placeholder replacements.
     */
    public function getOrCacheBaseMessageContent(Message $campaign, ?bool $forwardContent = false): ?MessagePrecacheDto
    {
        $cacheKey = sprintf('messaging.message.base.%d', $campaign->getId());

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        $domain = $this->configProvider->getValue(ConfigOption::Domain);

        $loadedMessageData = ($this->messageDataLoader)($campaign);
        $messagePrecacheDto = new MessagePrecacheDto();

        // parse the reply-to field into its components - email and name
        if (preg_match('/([^ ]+@[^ ]+)/', $loadedMessageData['replyto'], $regs)) {
            // if there is an email in the from, rewrite it as "name <email>"
            $loadedMessageData['replyto'] = str_replace($regs[0], '', $loadedMessageData['replyto']);
            $replyToEmail = $regs[0];
            // if the email has < and > take them out here
            $replyToEmail = str_replace('<', '', $replyToEmail);
            $replyToEmail = str_replace('>', '', $replyToEmail);
            $messagePrecacheDto->replyToEmail = $replyToEmail;
            // make sure there are no quotes around the name
            $messagePrecacheDto->replyToName = str_replace('"', '', ltrim(rtrim($loadedMessageData['replyto'])));
        } elseif (strpos($loadedMessageData['replyto'], ' ')) {
            // if there is a space, we need to add the email
            $messagePrecacheDto->replyToName = $loadedMessageData['replyto'];
            $messagePrecacheDto->replyToEmail = "listmaster@$domain";
        } elseif (!empty($loadedMessageData['replyto'])) {
            $messagePrecacheDto->replyToEmail = $loadedMessageData['replyto']."@$domain";
            //# makes more sense not to add the domain to the word, but the help says it does
            //# so let's keep it for now
            $messagePrecacheDto->replyToName = $loadedMessageData['replyto']."@$domain";
        }

        $messagePrecacheDto->fromName = $loadedMessageData['fromname'];
        $messagePrecacheDto->fromEmail = $loadedMessageData['fromemail'];
        $messagePrecacheDto->to = $loadedMessageData['tofield'];
        //0013076: different content when forwarding 'to a friend'
        $messagePrecacheDto->subject = $forwardContent ? stripslashes($loadedMessageData['forwardsubject']) : $loadedMessageData['subject'];
        //0013076: different content when forwarding 'to a friend'
        $messagePrecacheDto->content = $forwardContent ? stripslashes($loadedMessageData['forwardmessage']) : $loadedMessageData['message'];
        if ($this->useManualTextPart && !$forwardContent) {
            $messagePrecacheDto->textContent = $loadedMessageData['textmessage'];
        }
        //0013076: different content when forwarding 'to a friend'
        $messagePrecacheDto->footer = $forwardContent ? stripslashes($loadedMessageData['forwardfooter']) : $loadedMessageData['footer'];

        if (strip_tags($messagePrecacheDto->footer ) !== $messagePrecacheDto->footer) {
            $messagePrecacheDto->textFooter = ($this->htmlToText)($messagePrecacheDto->footer);
            $messagePrecacheDto->htmlFooter = $messagePrecacheDto->footer;
        } else {
            $messagePrecacheDto->textFooter = $messagePrecacheDto->footer;
            $messagePrecacheDto->htmlFooter = ($this->textParser)($messagePrecacheDto->footer);
        }

        $messagePrecacheDto->htmlFormatted = strip_tags($messagePrecacheDto->content) !== $messagePrecacheDto->content;
        $messagePrecacheDto->sendFormat = $loadedMessageData['sendformat'];

        if ($loadedMessageData['template']) {
            $template = $this->templateRepository->findOneById($loadedMessageData['template']);
            if ($template) {
                $messagePrecacheDto->template = stripslashes($template->getContent());
                $messagePrecacheDto->templateText = stripslashes($template->getText());
                $messagePrecacheDto->templateId = $template->getId();
            }
        }

        //# if we are sending a URL that contains user attributes, we cannot pre-parse the message here
        //# but that has quite some impact on speed. So check if that's the case and apply
        $messagePrecacheDto->userSpecificUrl = preg_match('/\[.+\]/', $loadedMessageData['sendurl']);

        if (!$messagePrecacheDto->userSpecificUrl) {
            //# Fetch external content here, because URL does not contain placeholders
            if (preg_match("/\[URL:([^\s]+)\]/i", $messagePrecacheDto->content, $regs)) {
                $remoteContent = ($this->remotePageFetcher)($regs[1], []);

                if ($remoteContent) {
                    $messagePrecacheDto->content = str_replace($regs[0], $remoteContent, $messagePrecacheDto->content);
                    $messagePrecacheDto->htmlFormatted = strip_tags($remoteContent) !== $remoteContent;

                    //# 17086 - disregard any template settings when we have a valid remote URL
                    $messagePrecacheDto->template  = null;
                    $messagePrecacheDto->templateText = null;
                    $messagePrecacheDto->templateId = null;
                } else {
                    $this->eventLogManager->log(
                        page: 'unknown page',
                        entry: 'Error fetching URL: '.$loadedMessageData['sendurl'].' cannot proceed',
                    );

                    return null;
                }
            }
        }

        $messagePrecacheDto->googleTrack = $loadedMessageData['google_track'];

        foreach (['subject', 'id', 'fromname', 'fromemail'] as $key) {
            $val = $loadedMessageData[$key];
            // Replace in content except for user-specific URL
            if (!$messagePrecacheDto->userSpecificUrl) {
                $messagePrecacheDto->content = str_ireplace("[$key]", $val, $messagePrecacheDto->content);
            }
            $messagePrecacheDto->textContent = str_ireplace("[$key]", $val, $messagePrecacheDto->textContent);
            $messagePrecacheDto->textFooter = str_ireplace("[$key]", $val, $messagePrecacheDto->textFooter);
            $messagePrecacheDto->htmlFooter = str_ireplace("[$key]", $val, $messagePrecacheDto->htmlFooter);
        }

        $ownerAttrValues = $this->adminAttributeDefRepository->getForAdmin($campaign->getOwner());
        foreach ($ownerAttrValues as $attr) {
            $messagePrecacheDto->adminAttributes['OWNER.'.$attr['name']] = $attr['value'];
        }

        $relatedAdmins = $this->adminRepository->getMessageRelatedAdmins($campaign->getId());
        if (count($relatedAdmins) === 1) {
            $listOwnerAttrValues = $this->adminAttributeDefRepository->getForAdmin($relatedAdmins[0]);
        } else {
            $listOwnerAttrValues = $this->adminAttributeDefRepository->getAllWIthEmptyValues();
        }

        foreach ($listOwnerAttrValues as $attr) {
            $messagePrecacheDto->adminAttributes['LISTOWNER.'.$attr['name']] = $attr['value'];
        }

        $baseurl = $this->configProvider->getValue(ConfigOption::Website);
        if ($this->uploadImageDir) {
            //# escape subdirectories, otherwise this renders empty
            $dir = str_replace('/', '\/', $this->uploadImageDir);
            $messagePrecacheDto->content = preg_replace(
                '/<img(.*)src="\/'.$dir.'(.*)>/iU',
                '<img\\1src="'.$this->publicSchema.'://'.$baseurl.'/'.$this->uploadImageDir.'\\2>',
                $messagePrecacheDto->content
            );
        }

        $messagePrecacheDto->content = $this->parseLogoPlaceholders($messagePrecacheDto->content);
        $messagePrecacheDto->template = $this->parseLogoPlaceholders($messagePrecacheDto->template);
        $messagePrecacheDto->htmlFooter = $this->parseLogoPlaceholders($messagePrecacheDto->htmlFooter);

//        $replacements = $this->buildBasicReplacements($campaign, $subject);
//        $html = $this->applyReplacements($html, $replacements);
//        $text = $this->applyReplacements($text, $replacements);
//        $footer = $this->applyReplacements($footer, $replacements);

        $this->cache->set($cacheKey, $messagePrecacheDto);

        return $messagePrecacheDto;
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

    private function parseLogoPlaceholders($content)
    {
        //# replace Logo placeholders
        preg_match_all('/\[LOGO\:?(\d+)?\]/', $content, $logoInstances);
        foreach ($logoInstances[0] as $index => $logoInstance) {
            $size = sprintf('%d', $logoInstances[1][$index]);
            $logoSize = !empty($size) ? $size : '500';
            $this->createCachedLogoImage((int)$logoSize);
            $content = str_replace($logoInstance, 'ORGANISATIONLOGO'.$logoSize.'.png', $content);
        }

        return $content;
    }

    private function createCachedLogoImage(int $size): void
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
