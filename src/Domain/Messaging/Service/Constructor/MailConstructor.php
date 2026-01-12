<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Constructor;

use PhpList\Core\Domain\Common\Html2Text;
use PhpList\Core\Domain\Common\RemotePageFetcher;
use PhpList\Core\Domain\Common\TextParser;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Service\MessagePlaceholderProcessor;
use PhpList\Core\Domain\Messaging\Exception\RemotePageFetchException;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use Symfony\Component\Mime\Email;
// todo: check this class
class MailConstructor
{
    public function __construct(
        private readonly SubscriberRepository $subscriberRepository,
        private readonly RemotePageFetcher $remotePageFetcher,
        private readonly EventLogManager $eventLogManager,
        private readonly ConfigProvider $configProvider,
        private readonly Html2Text $html2Text,
        private readonly TextParser $textParser,
        private readonly MessagePlaceholderProcessor $placeholderProcessor,
        private readonly bool $forwardAlternativeContent,
    ) {
    }

    public function build(
        Subscriber $subscriber,
        MessagePrecacheDto $messagePrecacheDto,
        ?string $hash = null
    ): Email {
        $defaultstyle = $this->configProvider->getValue(ConfigOption::HtmlEmailStyle);
        $adddefaultstyle = 0;

        $content = $messagePrecacheDto->content;
        $text = [];
        $html = [];
        if ($messagePrecacheDto->userSpecificUrl) {
            $userData = $this->subscriberRepository->getDataById($subscriber->getId());
            $this->replaceUserSpecificRemoteContent($messagePrecacheDto, $subscriber, $userData);
        }

        if ($hash !== 'forwarded') {
            $text['footer'] = $messagePrecacheDto->textFooter;
            $html['footer'] = $messagePrecacheDto->htmlFooter;
        } else {
            //0013076: different content when forwarding 'to a friend'
            if ($this->forwardAlternativeContent) {
                $text['footer'] = stripslashes($messagePrecacheDto->footer);
            } else {
                $text['footer'] = $this->configProvider->getValue(ConfigOption::ForwardFooter);
            }
            $html['footer'] = $text['footer'];
        }

        $hasText = !empty($messagePrecacheDto->textContent);
        if ($messagePrecacheDto->htmlFormatted) {
            $textcontent = $hasText ? $messagePrecacheDto->textContent : ($this->html2Text)($content);
            $htmlcontent = $content;
        } else {
            $textcontent = $hasText ? $content : $messagePrecacheDto->textContent;
            $htmlcontent = ($this->textParser)($content);
        }

        if ($messagePrecacheDto->template) {
            // template used
            // use only the content of the body element if it is present
            if (preg_match('|<body.*?>(.+)</body>|is', $htmlcontent, $matches)) {
                $htmlcontent = $matches[1];
            }
            $htmlmessage = str_replace('[CONTENT]', $htmlcontent, $messagePrecacheDto->template);
        } else {
            // no template used
            $htmlmessage = $htmlcontent;
            $adddefaultstyle = 1;
        }
        if ($messagePrecacheDto->templateText) {
            // text template used
            $textmessage = str_replace('[CONTENT]', $textcontent, $messagePrecacheDto->templateText);
        } else {
            // no text template used
            $textmessage = $textcontent;
        }

        $htmlmessage = str_ireplace('[FOOTER]', $html['footer'], $htmlmessage);
        $textmessage = str_ireplace('[FOOTER]', $text['footer'], $textmessage);

        $mail = new Email();
        return $mail;
    }

    private function replaceUserSpecificRemoteContent(
        MessagePrecacheDto $messagePrecacheDto,
        Subscriber $subscriber,
        array $userData
    ): void {
        if (!preg_match_all('/\[URL:([^\s]+)\]/i', $messagePrecacheDto->content, $matches, PREG_SET_ORDER)) {
            return;
        }

        $content = $messagePrecacheDto->content;
        foreach ($matches as $match) {
            $token = $match[0];
            $rawUrl = $match[1];

            if (!$rawUrl) {
                continue;
            }

            $url = preg_match('/^https?:\/\//i', $rawUrl) ? $rawUrl : 'http://' . $rawUrl;

            $remoteContent = ($this->remotePageFetcher)($url, $userData);

            if ($remoteContent === null) {
                $this->eventLogManager->log(
                    '',
                    sprintf('Error fetching URL: %s to send to %s', $rawUrl, $subscriber->getEmail())
                );

                throw new RemotePageFetchException();
            }

            $content = str_replace($token, '<!--' . $url . '-->' . $remoteContent, $content);
        }

        $messagePrecacheDto->content = $content;
        $messagePrecacheDto->htmlFormatted = strip_tags($content) !== $content;
    }
}
