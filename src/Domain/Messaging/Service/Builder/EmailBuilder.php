<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Builder;

use PhpList\Core\Domain\Common\PdfGenerator;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\LegacyUrlBuilder;
use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Exception\AttachmentException;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Messaging\Service\AttachmentAdder;
use PhpList\Core\Domain\Messaging\Service\Constructor\MailContentBuilderInterface;
use PhpList\Core\Domain\Messaging\Service\TemplateImageEmbedder;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Repository\UserBlacklistRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;

/** @SuppressWarnings("ExcessiveParameterList") */
class EmailBuilder extends SystemEmailBuilder
{
    public function __construct(
        ConfigProvider $configProvider,
        EventLogManager $eventLogManager,
        UserBlacklistRepository $blacklistRepository,
        SubscriberHistoryManager $subscriberHistoryManager,
        SubscriberRepository $subscriberRepository,
        MailContentBuilderInterface $mailConstructor,
        TemplateImageEmbedder $templateImageEmbedder,
        LoggerInterface $logger,
        protected readonly LegacyUrlBuilder $urlBuilder,
        protected readonly PdfGenerator $pdfGenerator,
        protected readonly AttachmentAdder $attachmentAdder,
        string $googleSenderId,
        bool $useAmazonSes,
        bool $usePrecedenceHeader,
        bool $devVersion = true,
        ?string $devEmail = null,
    ) {
        parent::__construct(
            configProvider: $configProvider,
            eventLogManager: $eventLogManager,
            blacklistRepository: $blacklistRepository,
            subscriberHistoryManager: $subscriberHistoryManager,
            subscriberRepository: $subscriberRepository,
            mailConstructor: $mailConstructor,
            templateImageEmbedder: $templateImageEmbedder,
            logger: $logger,
            googleSenderId: $googleSenderId,
            useAmazonSes: $useAmazonSes,
            usePrecedenceHeader: $usePrecedenceHeader,
            devVersion: $devVersion,
            devEmail: $devEmail,
        );
    }

    public function applyCampaignHeaders(Email $email, Subscriber $subscriber): Email
    {
        $preferencesUrl = $this->configProvider->getValue(ConfigOption::PreferencesUrl) ?? '';
        $unsubscribeUrl = $this->configProvider->getValue(ConfigOption::UnsubscribeUrl) ?? '';
        $subscribeUrl = $this->configProvider->getValue(ConfigOption::SubscribeUrl) ?? '';
        $adminAddress = $this->configProvider->getValue(ConfigOption::UnsubscribeUrl) ?? '';

        $email->getHeaders()->addTextHeader(
            'List-Help',
            '<' . $this->urlBuilder->withUid($preferencesUrl, $subscriber->getUniqueId()) . '>'
        );
        $email->getHeaders()->addTextHeader(
            'List-Unsubscribe',
            '<' . $this->urlBuilder->withUid($unsubscribeUrl, $subscriber->getUniqueId()) . '&jo=1>'
        );
        $email->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        $email->getHeaders()->addTextHeader('List-Subscribe', '<'. $subscribeUrl . '>');
        $email->getHeaders()->addTextHeader('List-Owner', '<mailto:'. $adminAddress .'>');

        return $email;
    }

    protected function applyContentAndFormatting(
        Email $email,
        ?string $htmlMessage,
        ?string $textMessage,
        int $messageId,
        MessagePrecacheDto $data,
        bool $htmlPref = false
    ): void {
        $domain = substr(strrchr($email->getTo()[0]->getAddress(), "@"), 1);
        $textDomains = explode("\n", trim($this->configProvider->getValue(ConfigOption::AlwaysSendTextDomains)));
        if (in_array($domain, $textDomains)) {
            $htmlPref = false;
        }

        $sentAs = '';
        // so what do we actually send?
        switch ($data->sendFormat) {
            case 'PDF':
                // send a PDF file to users who want html and text to everyone else
                if ($htmlPref) {
                    $sentAs = 'aspdf';
                    $pdfFile = $this->pdfGenerator->createPdfBytes($textMessage);
                    if (is_file($pdfFile) && filesize($pdfFile)) {
                        $fp = fopen($pdfFile, 'r');
                        if ($fp) {
                            $contents = fread($fp, filesize($pdfFile));
                            fclose($fp);
                            unlink($pdfFile);
                            $html = '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
                            <html lang="">
                                <head>
                                    <title></title>
                                </head>
                                <body>
                                    <embed src="message.pdf" width="450" height="450" href="message.pdf"></embed>
                                </body>
                            </html>';

                            $email->attach($contents, 'message.pdf', 'application/pdf');
                        }
                    }
                    if (!$this->attachmentAdder->add($email, $messageId, OutputFormat::Html)) {
                        throw new AttachmentException();
                    }
                } else {
                    $sentAs = 'astext';
                    $email->text($textMessage);
                    if (!$this->attachmentAdder->add($email, $messageId, OutputFormat::Text)) {
                        throw new AttachmentException();
                    }
                }
                break;
            case 'text and PDF':
                // send a PDF file to users who want html and text to everyone else
                if ($htmlPref) {
                    $sentAs = 'astextandpdf';
                    $pdfFile = $this->pdfGenerator->createPdfBytes($textMessage);
                    if (is_file($pdfFile) && filesize($pdfFile)) {
                        $fp = fopen($pdfFile, 'r');
                        if ($fp) {
                            $contents = fread($fp, filesize($pdfFile));
                            fclose($fp);
                            unlink($pdfFile);
                            $html = '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
                            <html lang="">
                                <head>
                                    <title></title>
                                </head>
                                <body>
                                    <embed src="message.pdf" width="450" height="450" href="message.pdf"></embed>
                                </body>
                            </html>';
                            $email->text($textMessage);
                            $email->attach($contents, 'message.pdf', 'application/pdf');
                        }
                    }
                    if (!$this->attachmentAdder->add($email, $messageId, OutputFormat::Html)) {
                        throw new AttachmentException();
                    }
                } else {
                    $sentAs = 'astext';
                    $email->text($textMessage);
                    if (!$this->attachmentAdder->add($email, $messageId, OutputFormat::Text)) {
                        throw new AttachmentException();
                    }
                }
                break;
            case 'text':
                $sentAs = 'astext';
                $email->text($textMessage);
                if (!$this->attachmentAdder->add($email, $messageId, OutputFormat::Text)) {
                    throw new AttachmentException();
                }
                break;
            case 'both':
            case 'text and HTML':
            case 'HTML':
            default:
                if ($htmlPref && $htmlMessage) {
                    $sentAs = 'astextandhtml';
                    $htmlMessage = ($this->templateImageEmbedder)(html: $htmlMessage, messageId: $messageId);
                    $email->html($htmlMessage);
                    $email->text($textMessage);
                    if (!$this->attachmentAdder->add($email, $messageId, OutputFormat::Html)) {
                        throw new AttachmentException();
                    }
                } else {
                    $sentAs = 'astext';
                    $email->text($textMessage);
                    if (!$this->attachmentAdder->add($email, $messageId, OutputFormat::Text)) {
                        throw new AttachmentException();
                    }
                }
                break;
        }
    }
}
