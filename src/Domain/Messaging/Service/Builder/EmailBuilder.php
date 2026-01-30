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
use PhpList\Core\Domain\Messaging\Service\Constructor\CampaignMailContentBuilder;
use PhpList\Core\Domain\Messaging\Service\TemplateImageEmbedder;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Repository\UserBlacklistRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\LogicException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @SuppressWarnings("ExcessiveParameterList")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class EmailBuilder extends BaseEmailBuilder
{
    public function __construct(
        ConfigProvider $configProvider,
        EventLogManager $eventLogManager,
        UserBlacklistRepository $blacklistRepository,
        SubscriberHistoryManager $subscriberHistoryManager,
        SubscriberRepository $subscriberRepository,
        LoggerInterface $logger,
        protected readonly CampaignMailContentBuilder $mailContentBuilder,
        protected readonly TemplateImageEmbedder $templateImageEmbedder,
        protected readonly LegacyUrlBuilder $urlBuilder,
        protected readonly PdfGenerator $pdfGenerator,
        protected readonly AttachmentAdder $attachmentAdder,
        protected readonly TranslatorInterface $translator,
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
            logger: $logger,
            googleSenderId: $googleSenderId,
            useAmazonSes: $useAmazonSes,
            usePrecedenceHeader: $usePrecedenceHeader,
            devVersion: $devVersion,
            devEmail: $devEmail,
        );
    }

    /** @return array{Email, OutputFormat}|null */
    public function buildCampaignEmail(
        int $messageId,
        MessagePrecacheDto $data,
        ?bool $skipBlacklistCheck = false,
        ?bool $inBlast = true,
        ?bool $htmlPref = false,
        ?bool $isTestMail = false,
    ): ?array {
        if (!$this->validateRecipientAndSubject(to: $data->to, subject: $data->subject)) {
            return null;
        }

        if (!$this->passesBlacklistCheck(to: $data->to, skipBlacklistCheck: $skipBlacklistCheck)) {
            return null;
        }

        $fromEmail = $data->fromEmail;
        $fromName = $data->fromName;
        $subject = (!$isTestMail ? '' : $this->translator->trans('(test)') .  ' ') . $data->subject;

        $email = $this->createBaseEmail(
            originalTo: $data->to,
            fromEmail: $fromEmail,
            fromName: $fromName,
            subject: $subject,
        );
        $this->addBaseCampaignHeaders(
            email: $email,
            messageId: $messageId,
            originalTo: $data->to,
            destinationEmail: $email->getTo()[0]->getAddress(),
            inBlast: $inBlast,
        );

        if (!empty($data->replyToEmail)) {
            $email->addReplyTo(new Address($data->replyToEmail, $data->replyToName));
        } elseif ($isTestMail) {
            $testReplyAddress = $this->configProvider->getValue(ConfigOption::AdminAddress);
            if (!empty($testReplyAddress)) {
                $email->addReplyTo(new Address($testReplyAddress, ''));
            }
        }

        [$htmlMessage, $textMessage] = ($this->mailContentBuilder)(messagePrecacheDto: $data, campaignId: $messageId);
        $sentAs = $this->applyContentAndFormatting(
            email: $email,
            htmlMessage: $htmlMessage,
            textMessage: $textMessage,
            messageId: $messageId,
            data: $data,
            htmlPref: $htmlPref,
        );

        return [$email, $sentAs];
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

    public function applyContentAndFormatting(
        Email $email,
        ?string $htmlMessage,
        ?string $textMessage,
        int $messageId,
        MessagePrecacheDto $data,
        bool $htmlPref = false,
        bool $forwarded = false,
    ): OutputFormat {
        $htmlPref = $this->shouldPreferHtml($htmlMessage, $htmlPref, $email);
        $normalizedFormat = $this->normalizeSendFormat($data->sendFormat);

        // so what do we actually send?
        switch ($normalizedFormat) {
            case 'pdf':
                $sentAs = $this->applyPdfFormat($email, $textMessage, $messageId, $htmlPref, $forwarded);
                break;
            case 'text_and_pdf':
                $sentAs = $this->applyTextAndPdfFormat($email, $textMessage, $messageId, $htmlPref, $forwarded);
                break;
            case 'text':
                $sentAs = OutputFormat::Text;
                $email->text($textMessage);
                if (!$this->attachmentAdder->add($email, $messageId, OutputFormat::Text, $forwarded)) {
                    throw new AttachmentException();
                }
                break;
            default:
                if ($htmlPref && $htmlMessage) {
                    $sentAs = OutputFormat::TextAndHtml;
                    $htmlMessage = ($this->templateImageEmbedder)(html: $htmlMessage, messageId: $messageId);
                    $email->html($htmlMessage);
                    $email->text($textMessage);
                    if (!$this->attachmentAdder->add($email, $messageId, OutputFormat::Html, $forwarded)) {
                        throw new AttachmentException();
                    }
                } else {
                    $sentAs = OutputFormat::Text;
                    $email->text($textMessage);
                    if (!$this->attachmentAdder->add($email, $messageId, OutputFormat::Text, $forwarded)) {
                        throw new AttachmentException();
                    }
                }
                break;
        }

        return $sentAs;
    }

    protected function shouldPreferHtml(?string $htmlMessage, bool $htmlPref, Email $email): bool
    {
        if (empty($email->getTo())) {
            throw new LogicException('No recipients specified');
        }
        // If we have HTML content, default to preferring HTML
        $htmlPref = $htmlPref || (is_string($htmlMessage) && trim($htmlMessage) !== '');

        // Domain-based text-only override
        $domain = substr(strrchr($email->getTo()[0]->getAddress(), '@'), 1);
        $textDomains = explode("\n", trim($this->configProvider->getValue(ConfigOption::AlwaysSendTextDomains) ?? ''));

        if (in_array($domain, $textDomains, true)) {
            return false;
        }

        return $htmlPref;
    }

    protected function normalizeSendFormat(?string $sendFormat): string
    {
        $format = strtolower(trim((string) $sendFormat));

        return match ($format) {
            'pdf'              => 'pdf',
            'text and pdf'     => 'text_and_pdf',
            'text'             => 'text',
            // the default is for these too:  'both', 'html', 'text and html',
            default            => 'html_and_text',
        };
    }

    protected function applyPdfFormat(
        Email $email,
        ?string $textMessage,
        int $messageId,
        bool $htmlPref,
        bool $forwarded
    ): OutputFormat {
        // send a PDF file to users who want html and text to everyone else
        if ($htmlPref) {
            $sentAs = OutputFormat::Pdf;
            $pdfBytes = $this->pdfGenerator->createPdfBytes($textMessage);
            $email->attach($pdfBytes, 'message.pdf', 'application/pdf');

            if (!$this->attachmentAdder->add($email, $messageId, OutputFormat::Html, $forwarded)) {
                throw new AttachmentException();
            }
        } else {
            $sentAs = OutputFormat::Text;
            $email->text($textMessage);
            if (!$this->attachmentAdder->add($email, $messageId, OutputFormat::Text, $forwarded)) {
                throw new AttachmentException();
            }
        }

        return $sentAs;
    }

    protected function applyTextAndPdfFormat(
        Email $email,
        ?string $textMessage,
        int $messageId,
        bool $htmlPref,
        bool $forwarded,
    ): OutputFormat {
        // send a PDF file to users who want html and text to everyone else
        if ($htmlPref) {
            $sentAs = OutputFormat::TextAndPdf;
            $pdfBytes = $this->pdfGenerator->createPdfBytes($textMessage);
            $email->attach($pdfBytes, 'message.pdf', 'application/pdf');
            $email->text($textMessage);

            if (!$this->attachmentAdder->add($email, $messageId, OutputFormat::Html, $forwarded)) {
                throw new AttachmentException();
            }
        } else {
            $sentAs = OutputFormat::Text;
            $email->text($textMessage);
            if (!$this->attachmentAdder->add($email, $messageId, OutputFormat::Text, $forwarded)) {
                throw new AttachmentException();
            }
        }

        return $sentAs;
    }
}
