<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Builder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Service\SystemMailConstructor;
use PhpList\Core\Domain\Messaging\Service\TemplateImageEmbedder;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Repository\UserBlacklistRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class EmailBuilder
{
    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly EventLogManager $eventLogManager,
        private readonly UserBlacklistRepository $blacklistRepository,
        private readonly SubscriberHistoryManager $subscriberHistoryManager,
        private readonly SubscriberRepository $subscriberRepository,
        private readonly SystemMailConstructor $systemMailConstructor,
        private readonly TemplateImageEmbedder $templateImageEmbedder,
        private readonly LoggerInterface $logger,
        private readonly string $googleSenderId,
        private readonly bool $useAmazonSes,
        private readonly bool $usePrecedenceHeader,
        private readonly bool $devVersion = true,
        private readonly ?string $devEmail = null,
    ) {
    }

    public function buildPhplistEmail(
        int $messageId,
        ?string $to = null,
        ?string $subject = null,
        ?string $message = null,
        ?bool $skipBlacklistCheck = false,
        ?bool $inBlast = true,
    ): ?Email {
        if (preg_match("/\n/", $to)) {
            $this->eventLogManager->log('', 'Error: invalid recipient, containing newlines, email blocked');

            return null;
        }
        if (preg_match("/\n/", $subject)) {
            $this->eventLogManager->log('', 'Error: invalid subject, containing newlines, email blocked');

            return null;
        }
        if (!$to) {
            $this->eventLogManager->log('', sprintf('Error: empty To: in message with subject %s to send', $subject));

            return null;
        }
        if (!$subject) {
            $this->eventLogManager->log('', "Error: empty Subject: in message to send to $to");

            return null;
        }
        if (!$skipBlacklistCheck && $this->blacklistRepository->isEmailBlacklisted($to)) {
            $this->eventLogManager->log('', sprintf('Error, %s is blacklisted, not sending', $to));
            $subscriber = $this->subscriberRepository->findOneByEmail($to);
            if (!$subscriber) {
                $this->logger->error('Error: subscriber not found', ['email' => $to]);

                return null;
            }
            $subscriber->setBlacklisted(true);

            $this->subscriberHistoryManager->addHistory(
                subscriber: $subscriber,
                message: 'Marked Blacklisted',
                details: 'Found user in blacklist while trying to send an email, marked black listed',
            );

            return null;
        }

        $fromEmail = $this->configProvider->getValue(ConfigOption::MessageFromAddress);
        $fromName = $this->configProvider->getValue(ConfigOption::MessageFromName);
        $messageReplyToAddress = $this->configProvider->getValue(ConfigOption::MessageReplyToAddress);
        if ($messageReplyToAddress) {
            $reply_to = $messageReplyToAddress;
        } else {
            $reply_to = $fromEmail;
        }
        $destinationEmail = '';

        if ($this->devVersion) {
            $message = "To: $to\n".$message;
            if ($this->devEmail) {
                $destinationEmail = $this->devEmail;
            }
        } else {
            $destinationEmail = $to;
        }

        list($htmlMessage, $textMessage) = ($this->systemMailConstructor)($message, $subject);

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
            '<' . $removeUrl . $sep . 'email=' . $destinationEmail . '&jo=1>'
        );


        if ($this->devEmail && $destinationEmail !== $this->devEmail) {
            $email->getHeaders()->addTextHeader('X-Originally to', $destinationEmail);
        }

        $newWrap = $this->configProvider->getValue(ConfigOption::WordWrap);
        if ($newWrap) {
            $textMessage = wordwrap($textMessage, (int)$newWrap);
        }

        if (!empty($htmlMessage)) {
            $email->html($htmlMessage);
            $email->text($textMessage);
            ($this->templateImageEmbedder)(html: $htmlMessage, messageId: $messageId);
            //# In the above phpMailer strips all tags, which removes the links which are wrapped in < and > by HTML2text
            //# so add it again
            $email->text($textMessage);
        }
        $email->text($textMessage);

        $email->to($destinationEmail);
        $email->from(new Address($fromEmail, $fromName));
        $email->subject($subject);

        return $email;
    }
}
