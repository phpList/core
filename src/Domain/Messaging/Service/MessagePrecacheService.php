<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Common\Html2Text;
use PhpList\Core\Domain\Common\RemotePageFetcher;
use PhpList\Core\Domain\Common\TextParser;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Identity\Repository\AdminAttributeDefinitionRepository;
use PhpList\Core\Domain\Identity\Repository\AdministratorRepository;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Repository\TemplateRepository;
use PhpList\Core\Domain\Messaging\Service\Manager\TemplateImageManager;
use Psr\SimpleCache\CacheInterface;

/** @SuppressWarnings("ExcessiveParameterList") */
class MessagePrecacheService
{
    private const REPLACE_KEYS = ['subject', 'id', 'fromname', 'fromemail'];

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly ConfigProvider $configProvider,
        private readonly Html2Text $html2Text,
        private readonly TextParser $textParser,
        private readonly TemplateRepository $templateRepository,
        private readonly RemotePageFetcher $remotePageFetcher,
        private readonly EventLogManager $eventLogManager,
        private readonly AdminAttributeDefinitionRepository $adminAttreDefRepository,
        private readonly AdministratorRepository $adminRepository,
        private readonly TemplateImageManager $templateImageManager,
        private readonly bool $useManualTextPart,
        private readonly string $uploadImageDir,
        private readonly string $publicSchema,
    ) {
    }

    /**
     * Retrieve the base (unpersonalized) message content for a campaign from cache,
     * or cache it on first access. Handle [URL:] token fetch and basic placeholder replacements.
     *
     */
    public function precacheMessage(Message $campaign, $loadedMessageData, ?bool $forwardContent = false): bool
    {
        $cacheKey = sprintf('messaging.message.base.%d.%d', $campaign->getId(), (int) $forwardContent);
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $domain = $this->configProvider->getValue(ConfigOption::Domain);
        $messagePrecacheDto = new MessagePrecacheDto();

        $this->populateReplyTo($messagePrecacheDto, $loadedMessageData, $domain);
        $this->populateBasicFields($messagePrecacheDto, $loadedMessageData, (bool) $forwardContent);

        $messagePrecacheDto->htmlFormatted = $this->isHtml($messagePrecacheDto->content);
        $messagePrecacheDto->sendFormat = $loadedMessageData['sendformat'];

        $this->applyTemplate($messagePrecacheDto, $loadedMessageData);

        //# if we are sending a URL that contains user attributes, we cannot pre-parse the message here
        //# but that has quite some impact on speed. So check if that's the case and apply
        $messagePrecacheDto->userSpecificUrl = preg_match('/\[.+\]/', $loadedMessageData['sendurl']);

        if (!$messagePrecacheDto->userSpecificUrl) {
            if (!$this->applyRemoteContentIfPresent($messagePrecacheDto, $loadedMessageData)) {
                return false;
            }
        }

        $messagePrecacheDto->googleTrack = $loadedMessageData['google_track'];

        $this->applyBasicReplacements($messagePrecacheDto, $loadedMessageData);
        $this->populateAdminAttributes($messagePrecacheDto, $campaign);

        $baseurl = $this->configProvider->getValue(ConfigOption::Website);
        if ($this->uploadImageDir) {
            //# escape subdirectories, otherwise this renders empty
            $dir = str_replace('/', '\/', $this->uploadImageDir);
            $messagePrecacheDto->content = preg_replace(
                '/<img(.*)src="\/' . $dir . '(.*)>/iU',
                '<img\\1src="' . $this->publicSchema . '://' . $baseurl . '/' . $this->uploadImageDir . '\\2>',
                $messagePrecacheDto->content
            );
        }

        $messagePrecacheDto->content = $this->templateImageManager->parseLogoPlaceholders($messagePrecacheDto->content);
        $messagePrecacheDto->template = $this->templateImageManager
            ->parseLogoPlaceholders($messagePrecacheDto->template);
        $messagePrecacheDto->htmlFooter = $this->templateImageManager
            ->parseLogoPlaceholders($messagePrecacheDto->htmlFooter);

        $this->cache->set($cacheKey, $messagePrecacheDto);

        return true;
    }

    private function isHtml(string $content): bool
    {
        return strip_tags($content) !== $content;
    }

    private function populateReplyTo(MessagePrecacheDto $messagePrecacheDto, $loadedMessageData, ?string $domain): void
    {
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
        } elseif (str_contains($loadedMessageData['replyto'], ' ')) {
            // if there is a space, we need to add the email
            $messagePrecacheDto->replyToName = $loadedMessageData['replyto'];
            $messagePrecacheDto->replyToEmail = 'listmaster@' . $domain;
        } elseif (!empty($loadedMessageData['replyto'])) {
            $messagePrecacheDto->replyToEmail = $loadedMessageData['replyto'] . '@' . $domain;
            //# makes more sense not to add the domain to the word, but the help says it does
            //# so let's keep it for now
            $messagePrecacheDto->replyToName = $loadedMessageData['replyto'] . '@' . $domain;
        }
    }

    private function populateBasicFields(
        MessagePrecacheDto $messagePrecacheDto,
        array $loadedMessageData,
        bool $forwardContent,
    ): void {
        $messagePrecacheDto->fromName = $loadedMessageData['fromname'];
        $messagePrecacheDto->fromEmail = $loadedMessageData['fromemail'];
        $messagePrecacheDto->to = $loadedMessageData['tofield'];

        //0013076: different content when forwarding 'to a friend'
        $messagePrecacheDto->subject = $forwardContent
            ? stripslashes($loadedMessageData['forwardsubject'])
            : $loadedMessageData['subject'];

        //0013076: different content when forwarding 'to a friend'
        $messagePrecacheDto->content = $forwardContent
            ? stripslashes($loadedMessageData['forwardmessage'])
            : $loadedMessageData['message'];
        if ($this->useManualTextPart && !$forwardContent) {
            $messagePrecacheDto->textContent = $loadedMessageData['textmessage'];
        }

        //0013076: different content when forwarding 'to a friend'
        $messagePrecacheDto->footer = $forwardContent
            ? stripslashes($loadedMessageData['forwardfooter'])
            : $loadedMessageData['footer'];

        if ($this->isHtml($messagePrecacheDto->footer)) {
            $messagePrecacheDto->textFooter = ($this->html2Text)($messagePrecacheDto->footer);
            $messagePrecacheDto->htmlFooter = $messagePrecacheDto->footer;
        } else {
            $messagePrecacheDto->textFooter = $messagePrecacheDto->footer;
            $messagePrecacheDto->htmlFooter = ($this->textParser)($messagePrecacheDto->footer);
        }
    }

    private function applyTemplate(MessagePrecacheDto $messagePrecacheDto, $loadedMessageData): void
    {
        if ($loadedMessageData['template']) {
            $template = $this->templateRepository->findOneById($loadedMessageData['template']);
            if ($template) {
                $messagePrecacheDto->template = stripslashes($template->getContent());
                $messagePrecacheDto->templateText = stripslashes($template->getText());
                $messagePrecacheDto->templateId = $template->getId();
            }
        }
    }

    private function applyRemoteContentIfPresent(MessagePrecacheDto $messagePrecacheDto, $loadedMessageData): bool
    {
        if (preg_match('/\[URL:([^\s]+)\]/i', $messagePrecacheDto->content, $regs)) {
            $remoteContent = ($this->remotePageFetcher)($regs[1], []);

            if ($remoteContent) {
                $messagePrecacheDto->content = str_replace($regs[0], $remoteContent, $messagePrecacheDto->content);
                $messagePrecacheDto->htmlFormatted = $this->isHtml($remoteContent);

                //# 17086 - disregard any template settings when we have a valid remote URL
                $messagePrecacheDto->template  = null;
                $messagePrecacheDto->templateText = null;
                $messagePrecacheDto->templateId = null;
            } else {
                $this->eventLogManager->log(
                    page: 'unknown page',
                    entry: 'Error fetching URL: '.$loadedMessageData['sendurl'].' cannot proceed',
                );

                return false;
            }
        }

        return true;
    }

    private function applyBasicReplacements(MessagePrecacheDto $messagePrecacheDto, $loadedMessageData): void
    {
        foreach (self::REPLACE_KEYS as $key) {
            $replace = $loadedMessageData[$key];
            $searchKey = '['. $key . ']';
            // Replace in content except for user-specific URL
            if (!$messagePrecacheDto->userSpecificUrl) {
                $messagePrecacheDto->content = str_ireplace($searchKey, $replace, $messagePrecacheDto->content);
            }
            $messagePrecacheDto->textContent = str_ireplace($searchKey, $replace, $messagePrecacheDto->textContent);
            $messagePrecacheDto->textFooter = str_ireplace($searchKey, $replace, $messagePrecacheDto->textFooter);
            $messagePrecacheDto->htmlFooter = str_ireplace($searchKey, $replace, $messagePrecacheDto->htmlFooter);
        }
    }

    private function populateAdminAttributes(MessagePrecacheDto $messagePrecacheDto, Message $campaign): void
    {
        $ownerAttrValues = $this->adminAttreDefRepository->getForAdmin($campaign->getOwner());
        foreach ($ownerAttrValues as $attr) {
            $messagePrecacheDto->adminAttributes['OWNER.' . $attr['name']] = $attr['value'];
        }

        $relatedAdmins = $this->adminRepository->getMessageRelatedAdmins($campaign->getId());
        if (count($relatedAdmins) === 1) {
            $listOwnerAttrValues = $this->adminAttreDefRepository->getForAdmin($relatedAdmins[0]);
        } else {
            $listOwnerAttrValues = $this->adminAttreDefRepository->getAllWithEmptyValues();
        }

        foreach ($listOwnerAttrValues as $attr) {
            $messagePrecacheDto->adminAttributes['LISTOWNER.' . $attr['name']] = $attr['value'];
        }
    }
}
