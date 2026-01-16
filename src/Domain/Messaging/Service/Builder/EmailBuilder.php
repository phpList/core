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
use PhpList\Core\Domain\Messaging\Exception\DevEmailNotConfiguredException;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Messaging\Service\AttachmentAdder;
use PhpList\Core\Domain\Messaging\Service\Constructor\MailContentBuilderInterface;
use PhpList\Core\Domain\Messaging\Service\TemplateImageEmbedder;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Repository\UserBlacklistRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/** @SuppressWarnings("ExcessiveParameterList") */
class EmailBuilder
{
    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly EventLogManager $eventLogManager,
        private readonly UserBlacklistRepository $blacklistRepository,
        private readonly SubscriberHistoryManager $subscriberHistoryManager,
        private readonly SubscriberRepository $subscriberRepository,
        private readonly MailContentBuilderInterface $mailConstructor,
        private readonly TemplateImageEmbedder $templateImageEmbedder,
        private readonly LoggerInterface $logger,
        private readonly ConfigProvider $config,
        private readonly LegacyUrlBuilder $urlBuilder,
        private readonly PdfGenerator $pdfGenerator,
        private readonly AttachmentAdder $attachmentAdder,
        private readonly string $googleSenderId,
        private readonly bool $useAmazonSes,
        private readonly bool $usePrecedenceHeader,
        private readonly bool $devVersion = true,
        private readonly ?string $devEmail = null,
    ) {
    }

    public function buildPhplistEmail(
        int $messageId,
        MessagePrecacheDto $data,
        ?bool $skipBlacklistCheck = false,
        ?bool $inBlast = true,
        ?bool $htmlPref = false,
    ): ?Email {
        if (!$this->validateRecipientAndSubject(to: $data->to, subject: $data->subject)) {
            return null;
        }

        if (!$this->passesBlacklistCheck(to: $data->to, skipBlacklistCheck: $skipBlacklistCheck)) {
            return null;
        }

        $fromEmail = $this->configProvider->getValue(ConfigOption::MessageFromAddress);
        $fromName = $this->configProvider->getValue(ConfigOption::MessageFromName);
//        $messageReplyToAddress = $this->configProvider->getValue(ConfigOption::MessageReplyToAddress);
//        $replyTo = $messageReplyToAddress ?: $fromEmail;

        $destinationEmail = $this->resolveDestinationEmail($data->to);

        [$htmlMessage, $textMessage] = ($this->mailConstructor)(messagePrecacheDto: $data);

        $email = $this->createBaseEmail(
            messageId: $messageId,
            destinationEmail: $destinationEmail,
            fromEmail: $fromEmail,
            fromName: $fromName,
            subject: $data->subject,
            inBlast: $inBlast
        );

        $this->applyContentAndFormatting(
            email: $email,
            htmlMessage: $htmlMessage,
            textMessage: $textMessage,
            messageId: $messageId,
            data: $data,
            htmlPref: $htmlPref,
        );

        return $email;
    }

    public function applyCampaignHeaders(Email $email, Subscriber $subscriber): Email
    {
        $preferencesUrl = $this->config->getValue(ConfigOption::PreferencesUrl) ?? '';
        $unsubscribeUrl = $this->config->getValue(ConfigOption::UnsubscribeUrl) ?? '';
        $subscribeUrl = $this->config->getValue(ConfigOption::SubscribeUrl) ?? '';
        $adminAddress = $this->config->getValue(ConfigOption::UnsubscribeUrl) ?? '';

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

    private function validateRecipientAndSubject(?string $to, ?string $subject): bool
    {
        if (!$to || trim($to) === '') {
            $this->eventLogManager->log('', sprintf('Error: empty To: in message with subject %s to send', $subject));

            return false;
        }
        if (!$subject || trim($subject) === '') {
            $this->eventLogManager->log('', sprintf('Error: empty Subject: in message to send to %s', $to));

            return false;
        }
        if (preg_match("/\n/", $to)) {
            $this->eventLogManager->log('', 'Error: invalid recipient, containing newlines, email blocked');

            return false;
        }
        if (preg_match("/\n/", $subject)) {
            $this->eventLogManager->log('', 'Error: invalid subject, containing newlines, email blocked');

            return false;
        }

        return true;
    }

    private function passesBlacklistCheck(string $to, ?bool $skipBlacklistCheck): bool
    {

        if (!$skipBlacklistCheck && $this->blacklistRepository->isEmailBlacklisted($to)) {
            $this->eventLogManager->log('', sprintf('Error, %s is blacklisted, not sending', $to));
            $subscriber = $this->subscriberRepository->findOneByEmail($to);
            if (!$subscriber) {
                $this->logger->error('Error: subscriber not found', ['email' => $to]);

                return false;
            }
            $subscriber->setBlacklisted(true);

            $this->subscriberHistoryManager->addHistory(
                subscriber: $subscriber,
                message: 'Marked Blacklisted',
                details: 'Found user in blacklist while trying to send an email, marked black listed',
            );

            return false;
        }

        return true;
    }

    private function resolveDestinationEmail(?string $to): string
    {
        $destinationEmail = $to;

        if ($this->devVersion) {
            if (!$this->devEmail) {
                throw new DevEmailNotConfiguredException();
            }
            $destinationEmail = $this->devEmail;
        }

        return $destinationEmail;
    }

    private function createBaseEmail(
        int $messageId,
        mixed $destinationEmail,
        ?string $fromEmail,
        ?string $fromName,
        ?string $subject,
        ?bool $inBlast
    ) : Email {
        $email = (new Email());

        $email->getHeaders()->addTextHeader('X-MessageID', (string)$messageId);
        $email->getHeaders()->addTextHeader('X-ListMember', $destinationEmail);
        if ($this->googleSenderId !== '') {
            $email->getHeaders()->addTextHeader('Feedback-ID', sprintf('%s:%s', $messageId, $this->googleSenderId));
        }

        if (!$this->useAmazonSes && $this->usePrecedenceHeader) {
            $email->getHeaders()->addTextHeader('Precedence', 'bulk');
        }

        if ($inBlast) {
            $email->getHeaders()->addTextHeader('X-Blast', '1');
        }

        $removeUrl = $this->configProvider->getValue(ConfigOption::UnsubscribeUrl);
        $sep = !str_contains($removeUrl, '?') ? '?' : '&';
        $email->getHeaders()->addTextHeader(
            'List-Unsubscribe',
            sprintf(
                '<%s%s%s>',
                $removeUrl,
                $sep,
                http_build_query([
                    'email' => $destinationEmail,
                    'jo' => 1,
                ])
            )
        );

        if ($this->devEmail && $destinationEmail !== $this->devEmail) {
            $email->getHeaders()->addMailboxHeader(
                'X-Originally-To',
                new Address($destinationEmail)
            );
        }

        $email->to($destinationEmail);
        $email->from(new Address($fromEmail, $fromName));
        $email->subject($subject);

        return $email;
    }

    private function applyContentAndFormatting(
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
