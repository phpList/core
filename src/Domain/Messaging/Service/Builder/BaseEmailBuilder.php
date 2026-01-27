<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Builder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Exception\DevEmailNotConfiguredException;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Repository\UserBlacklistRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/** @SuppressWarnings("ExcessiveParameterList") */
abstract class BaseEmailBuilder
{
    public function __construct(
        protected readonly ConfigProvider $configProvider,
        protected readonly EventLogManager $eventLogManager,
        protected readonly UserBlacklistRepository $blacklistRepository,
        protected readonly SubscriberHistoryManager $subscriberHistoryManager,
        protected readonly SubscriberRepository $subscriberRepository,
        protected readonly LoggerInterface $logger,
        protected readonly string $googleSenderId,
        protected readonly bool $useAmazonSes,
        protected readonly bool $usePrecedenceHeader,
        protected readonly bool $devVersion = true,
        protected readonly ?string $devEmail = null,
    ) {
    }

    protected function validateRecipientAndSubject(?string $to, ?string $subject): bool
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

    protected function passesBlacklistCheck(string $to, ?bool $skipBlacklistCheck): bool
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

    protected function resolveDestinationEmail(?string $to): string
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

    protected function createBaseEmail(
        int $messageId,
        string $originalTo,
        ?string $fromEmail,
        ?string $fromName,
        ?string $subject,
        ?bool $inBlast
    ) : Email {
        $email = (new Email());
        $destinationEmail = $this->resolveDestinationEmail($originalTo);

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

        if ($this->devEmail && $destinationEmail === $this->devEmail && $originalTo !== $this->devEmail) {
            $email->getHeaders()->addMailboxHeader(
                'X-Originally-To',
                new Address($originalTo)
            );
        }

        $email->to($destinationEmail);
        $email->from(new Address($fromEmail, $fromName ?? ''));
        $email->subject($subject);

        return $email;
    }
}
