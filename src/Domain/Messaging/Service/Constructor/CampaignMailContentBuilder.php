<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Constructor;

use PhpList\Core\Domain\Common\Html2Text;
use PhpList\Core\Domain\Common\RemotePageFetcher;
use PhpList\Core\Domain\Common\TextParser;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Service\MessagePlaceholderProcessor;
use PhpList\Core\Domain\Messaging\Exception\RemotePageFetchException;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;

class CampaignMailContentBuilder implements MailContentBuilderInterface
{
    public function __construct(
        private readonly SubscriberRepository $subscriberRepository,
        private readonly RemotePageFetcher $remotePageFetcher,
        private readonly EventLogManager $eventLogManager,
        private readonly ConfigProvider $configProvider,
        private readonly Html2Text $html2Text,
        private readonly TextParser $textParser,
        private readonly MessagePlaceholderProcessor $placeholderProcessor,
    ) {
    }

    public function __invoke(
        MessagePrecacheDto $messagePrecacheDto,
        ?int $campaignId = null,
    ): array {
        $subscriber = $this->subscriberRepository->findOneByEmail($messagePrecacheDto->to);
        $addDefaultStyle = false;

        if ($messagePrecacheDto->userSpecificUrl) {
            $userData = $this->subscriberRepository->getDataById($subscriber->getId());
            $this->replaceUserSpecificRemoteContent($messagePrecacheDto, $subscriber, $userData);
        }

        $content = $messagePrecacheDto->content;
        $hasText = !empty($messagePrecacheDto->textContent);
        if ($messagePrecacheDto->htmlFormatted) {
            $textContent = $hasText ? $messagePrecacheDto->textContent : ($this->html2Text)($content);
            $htmlContent = $content;
        } else {
            $textContent = $hasText ? $content : $messagePrecacheDto->textContent;
            $htmlContent = ($this->textParser)($content);
        }

        if ($messagePrecacheDto->template) {
            // template used: use only the content of the body element if it is present
            if (preg_match('|<body.*?>(.+)</body>|is', $htmlContent, $matches)) {
                $htmlContent = $matches[1];
            }
            $htmlMessage = str_replace('[CONTENT]', $htmlContent, $messagePrecacheDto->template);
        } else {
            $htmlMessage = $htmlContent;
            $addDefaultStyle = true;
        }
        if ($messagePrecacheDto->templateText) {
            $textMessage = str_replace('[CONTENT]', $textContent, $messagePrecacheDto->templateText);
        } else {
            $textMessage = $textContent;
        }

        $textMessage = $this->placeholderProcessor->process(
            value: $textMessage,
            user: $subscriber,
            format: OutputFormat::Text,
            messagePrecacheDto: $messagePrecacheDto,
            campaignId: $campaignId,
        );

        $htmlMessage = $this->placeholderProcessor->process(
            value: $htmlMessage,
            user: $subscriber,
            format: OutputFormat::Html,
            messagePrecacheDto: $messagePrecacheDto,
            campaignId: $campaignId,
        );

        $htmlMessage = $this->ensureHtmlFormating(content: $htmlMessage, addDefaultStyle: $addDefaultStyle);
        // todo: add link CLICKTRACK to $htmlMessage

        return [$htmlMessage, $textMessage];
    }

    private function replaceUserSpecificRemoteContent(
        MessagePrecacheDto $messagePrecacheDto,
        Subscriber $subscriber,
        array $userData
    ): void {
        if (!preg_match_all('/\[URL:(^\s]+)]/i', $messagePrecacheDto->content, $matches, PREG_SET_ORDER)) {
            return;
        }

        $content = $messagePrecacheDto->content;
        foreach ($matches as $match) {
            $token = $match[0];
            $rawUrl = $match[1];

            if (!$rawUrl) {
                continue;
            }

            $url = preg_match('/^https?:\/\//i', $rawUrl) ? $rawUrl : 'https://' . $rawUrl;

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

    private function ensureHtmlFormating(string $content, bool $addDefaultStyle): string
    {
        if (!preg_match('#<body.*</body>#ims', $content)) {
            $content = '<body>' . $content . '</body>';
        }
        if (!preg_match('#<head.*</head>#ims', $content)) {
            $defaultStyle = $this->configProvider->getValue(ConfigOption::HtmlEmailStyle);

            if (!$addDefaultStyle) {
                $defaultStyle = '';
            }
            $content = '<head>
        <meta content="text/html;charset=UTF-8" http-equiv="Content-Type">
        <meta content="width=device-width"/>
        <title></title>' . $defaultStyle . '</head>' . $content;
        }
        if (!preg_match('#<html.*</html>#ims', $content)) {
            $content = '<html lang="en">' . $content . '</html>';
        }

        //# remove trailing code after </html>
        $content = preg_replace('#</html>.*#msi', '</html>', $content);

        //# the editor sometimes places <p> and </p> around the URL
        $content = str_ireplace('<p><!DOCTYPE', '<!DOCTYPE', $content);

        return str_ireplace('</html></p>', '</html>', $content);
    }
}
