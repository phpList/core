<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Builder;

use PhpList\Core\Domain\Common\PdfGenerator;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\LegacyUrlBuilder;
use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Messaging\Service\AttachmentAdder;
use PhpList\Core\Domain\Messaging\Service\Constructor\CampaignMailContentBuilder;
use PhpList\Core\Domain\Messaging\Service\TemplateImageEmbedder;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Repository\UserBlacklistRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @SuppressWarnings("ExcessiveParameterList")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class ForwardEmailBuilder extends EmailBuilder
{
    public function __construct(
        ConfigProvider $configProvider,
        EventLogManager $eventLogManager,
        UserBlacklistRepository $blacklistRepository,
        SubscriberHistoryManager $subscriberHistoryManager,
        SubscriberRepository $subscriberRepository,
        LoggerInterface $logger,
        CampaignMailContentBuilder $mailContentBuilder,
        TemplateImageEmbedder $templateImageEmbedder,
        LegacyUrlBuilder $urlBuilder,
        PdfGenerator $pdfGenerator,
        AttachmentAdder $attachmentAdder,
        TranslatorInterface $translator,
        private readonly HttpReceivedStampBuilder $httpReceivedStampBuilder,
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
            mailContentBuilder: $mailContentBuilder,
            templateImageEmbedder: $templateImageEmbedder,
            urlBuilder: $urlBuilder,
            pdfGenerator: $pdfGenerator,
            attachmentAdder: $attachmentAdder,
            translator: $translator,
            googleSenderId: $googleSenderId,
            useAmazonSes: $useAmazonSes,
            usePrecedenceHeader: $usePrecedenceHeader,
            devVersion: $devVersion,
            devEmail: $devEmail,
        );
    }

    /** @return array{Email, OutputFormat}|null */
    public function buildForwardEmail(
        int $messageId,
        string $email,
        Subscriber $forwardedBy,
        MessagePrecacheDto $data,
        bool $htmlPref,
        string $fromName,
        string $fromEmail,
        ?string $forwardedPersonalNote = null,
    ): ?array {
        if (!$this->validateRecipientAndSubject(to: $email, subject: $data->subject)) {
            return null;
        }

        if (!$this->passesBlacklistCheck(to: $email, skipBlacklistCheck: false)) {
            return null;
        }

        $subject = $this->translator->trans('Fwd') . ': ' . stripslashes($data->subject);

        [$htmlMessage, $textMessage] = ($this->mailContentBuilder)(
            messagePrecacheDto: $data,
            campaignId: $messageId,
            forwardedBy: $forwardedBy,
            forwardedPersonalNote: $forwardedPersonalNote,
        );

        $email = $this->createBaseEmail(
            originalTo: $data->to,
            fromEmail: $fromEmail,
            fromName: $fromName,
            subject: $subject,
        );

        $this->applyCampaignHeaders($email, $forwardedBy);

        $email->addReplyTo(new Address($fromEmail, $fromName));
        $this->applyCampaignHeaders($email, $forwardedBy);

        $sentAs = $this->applyContentAndFormatting(
            email: $email,
            htmlMessage: $htmlMessage,
            textMessage: $textMessage,
            messageId: $messageId,
            data: $data,
            htmlPref: $htmlPref,
            forwarded: true,
        );

        return [$email, $sentAs];
    }

    public function applyCampaignHeaders(Email $email, Subscriber $subscriber): Email
    {
        $email = parent::applyCampaignHeaders($email, $subscriber);

        $receivedLine = $this->httpReceivedStampBuilder->buildStamp();
        $email->getHeaders()->addTextHeader('Received', $receivedLine);

        return $email;
    }
}
