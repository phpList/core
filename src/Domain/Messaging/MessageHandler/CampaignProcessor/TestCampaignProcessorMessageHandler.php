<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\MessageHandler\CampaignProcessor;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Exception\AttachmentCopyException;
use PhpList\Core\Domain\Messaging\Exception\MessageCacheMissingException;
use PhpList\Core\Domain\Messaging\Exception\MessageSizeLimitExceededException;
use PhpList\Core\Domain\Messaging\Message\CampaignProcessor\TestCampaignProcessorMessage;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\MessageStatus;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Service\Builder\EmailBuilder;
use PhpList\Core\Domain\Messaging\Service\Builder\SystemEmailBuilder;
use PhpList\Core\Domain\Messaging\Service\MailSizeChecker;
use PhpList\Core\Domain\Messaging\Service\MessageDataLoader;
use PhpList\Core\Domain\Messaging\Service\MessagePrecacheService;
use PhpList\Core\Domain\Messaging\Service\MessageProcessingPreparator;
use PhpList\Core\Domain\Messaging\Service\RateLimitedCampaignMailer;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Service\Provider\SubscriberProvider;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
 */
#[AsMessageHandler]
class TestCampaignProcessorMessageHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly RateLimitedCampaignMailer $rateLimitedCampaignMailer,
        private readonly EntityManagerInterface $entityManager,
        private readonly SubscriberProvider $subscriberProvider,
        private readonly MessageProcessingPreparator $messagePreparator,
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $cache,
        private readonly TranslatorInterface $translator,
        private readonly MessageRepository $messageRepository,
        private readonly MessagePrecacheService $precacheService,
        private readonly MessageDataLoader $messageDataLoader,
        private readonly SystemEmailBuilder $systemEmailBuilder,
        private readonly EmailBuilder $campaignEmailBuilder,
        private readonly MailSizeChecker $mailSizeChecker,
        private readonly ConfigProvider $configProvider,
        #[Autowire('%imap_bounce.email%')] private readonly string $bounceEmail,
    ) {
    }

    public function __invoke(TestCampaignProcessorMessage $data): void
    {
        $campaign = $this->messageRepository->findByIdAndStatus($data->getMessageId(), MessageStatus::Submitted);
        if (!$campaign) {
            $this->logger->warning(
                $this->translator->trans('Campaign not found or not in submitted status'),
                ['campaign_id' => $data->getMessageId()]
            );

            return;
        }

        $loadedMessageData = ($this->messageDataLoader)($campaign);

        $cacheKey = sprintf('messaging.message.base.%d.%d', $campaign->getId(), 0);
        if (!$this->precacheService->precacheMessage($campaign, $loadedMessageData)) {
            return;
        }

        $subscribers = $this->subscriberProvider->getSubscribersForMessageOrLists($data, $campaign);

        $this->processSubscribersForCampaign($campaign, $subscribers, $cacheKey);
    }

    private function handleEmailSending(
        Message $campaign,
        Subscriber $subscriber,
        MessagePrecacheDto $precachedContent,
    ): void {
        // todo: check at which point link tracking should be applied (maybe after constructing full text?)
        $processed = $this->messagePreparator->processMessageLinks(
            campaignId: $campaign->getId(),
            cachedMessageDto: $precachedContent,
            subscriber: $subscriber
        );
        $this->entityManager->flush();

        try {
            $result = $this->campaignEmailBuilder->buildCampaignEmail(
                messageId: $campaign->getId(),
                data: $processed,
                toEmail: $subscriber->getEmail(),
                skipBlacklistCheck: false,
                inBlast: true,
                htmlPref: $subscriber->hasHtmlEmail(),
            );
            if ($result === null) {
                return;
            }
            $email = $result[0];
            $this->campaignEmailBuilder->applyCampaignHeaders(email: $email, subscriber: $subscriber);

            $this->rateLimitedCampaignMailer->send($email);
            ($this->mailSizeChecker)($campaign, $email, $subscriber->hasHtmlEmail());
        } catch (MessageSizeLimitExceededException $e) {
            // stop after the first message if size is exceeded
            $this->logger->error($e->getMessage(), [
                'campaign_id' => $campaign->getId(),
            ]);
            throw $e;
        } catch (AttachmentCopyException $e) {
            // stop after the first message if size is exceeded
            $data = new MessagePrecacheDto();
            $data->subject = $this->translator->trans('phpList system error');
            $data->content = $this->translator->trans($e->getMessage());

            $email = $this->systemEmailBuilder->buildCampaignEmail(
                messageId: $campaign->getId(),
                data: $data,
                toEmail: $this->configProvider->getValue(ConfigOption::ReportAddress) ?? '',
            );

            $envelope = new Envelope(
                sender: new Address($this->bounceEmail, 'PHPList'),
                recipients: [new Address($email->getTo()[0]->getAddress())],
            );
            $this->mailer->send(message: $email, envelope: $envelope);

            throw $e;
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage(), [
                'subscriber_id' => $subscriber->getId(),
                'campaign_id' => $campaign->getId(),
            ]);
            $this->logger->warning($this->translator->trans('Failed to send to: %email%', [
                '%email%' => $subscriber->getEmail(),
            ]));
        }
    }

    private function processSubscribersForCampaign(Message $campaign, array $subscribers, string $cacheKey): void
    {
        foreach ($subscribers as $subscriber) {
            if (!filter_var($subscriber->getEmail(), FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $messagePrecacheDto = $this->cache->get($cacheKey);
            if ($messagePrecacheDto === null) {
                throw new MessageCacheMissingException();
            }

            $this->handleEmailSending($campaign, $subscriber, $messagePrecacheDto);
        }
    }
}
