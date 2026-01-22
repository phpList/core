<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Builder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Messaging\Service\Constructor\MailContentBuilderInterface;
use PhpList\Core\Domain\Messaging\Service\TemplateImageEmbedder;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Repository\UserBlacklistRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;

/** @SuppressWarnings("ExcessiveParameterList") */
class SystemEmailBuilder extends BaseEmailBuilder
{
    public function __construct(
        ConfigProvider $configProvider,
        EventLogManager $eventLogManager,
        UserBlacklistRepository $blacklistRepository,
        SubscriberHistoryManager $subscriberHistoryManager,
        SubscriberRepository $subscriberRepository,
        protected readonly MailContentBuilderInterface $mailConstructor,
        protected readonly TemplateImageEmbedder $templateImageEmbedder,
        LoggerInterface $logger,
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

        [$htmlMessage, $textMessage] = ($this->mailConstructor)(messagePrecacheDto: $data);

        $email = $this->createBaseEmail(
            messageId: $messageId,
            originalTo: $data->to,
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
        );

        return $email;
    }

    protected function applyContentAndFormatting(
        Email $email,
        ?string $htmlMessage,
        ?string $textMessage,
        int $messageId,
    ): void {
        // Word wrapping disabled here to avoid reliance on config provider during content assembly
        if (!empty($htmlMessage)) {
            // Embed/transform images and use the returned HTML content
            $htmlMessage = ($this->templateImageEmbedder)(html: $htmlMessage, messageId: $messageId);
            $email->html($htmlMessage);
            //# In the above phpMailer strips all tags, which removes the links
            // which are wrapped in < and > by HTML2text so add it again
        }
        // Ensure text body is always set
        $email->text($textMessage);
    }
}
