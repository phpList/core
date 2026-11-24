<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Command;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Message\CampaignProcessor\SyncCampaignProcessorMessage;
use PhpList\Core\Domain\Messaging\Model\Message\MessageStatus;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Service\MessageProcessingPreparator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Foreground equivalent of phplist3's `phplist -pprocessqueue`: sends the due campaigns in this
 * process (synchronously) instead of handing them to the async Messenger transport like
 * `phplist:process-queue` does.
 *
 * Deviations from phplist3: no `-f` (kill other processes) and no plugin hooks; batch size and
 * throttling are handled by the sending loop; an optional --campaign limits the run to one campaign.
 */
#[AsCommand(
    name: 'phplist:send-campaigns',
    description: 'Sends submitted campaigns immediately in the current process.'
)]
class SendCampaignsCommand extends Command
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly LockFactory $lockFactory,
        private readonly MessageProcessingPreparator $messagePreparator,
        private readonly MessageBusInterface $messageBus,
        private readonly ConfigProvider $configProvider,
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'campaign',
            'c',
            InputOption::VALUE_REQUIRED,
            'Only send the submitted campaign with this id'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $campaignId = $this->parseCampaignId($input->getOption('campaign'));
        if ($campaignId === false) {
            $output->writeln($this->translator->trans('The campaign option must be a positive integer.'));

            return Command::INVALID;
        }

        $lock = $this->lockFactory->createLock('queue_processor');
        if (!$lock->acquire()) {
            $output->writeln($this->translator->trans('Queue is already being processed by another instance.'));

            return Command::FAILURE;
        }

        try {
            return $this->sendCampaigns($campaignId, $output);
        } catch (Throwable $throwable) {
            $output->writeln($throwable->getMessage());

            return Command::FAILURE;
        } finally {
            $lock->release();
        }
    }

    /** @return int|null|false null when not given, false when invalid */
    private function parseCampaignId(mixed $value): int|false|null
    {
        if ($value === null) {
            return null;
        }

        return ctype_digit((string) $value) && (int) $value > 0 ? (int) $value : false;
    }

    private function sendCampaigns(?int $campaignId, OutputInterface $output): int
    {
        if ($this->configProvider->isEnabled(ConfigOption::MaintenanceMode)) {
            $output->writeln(
                $this->translator->trans('The system is in maintenance mode, stopping. Try again later.')
            );

            return Command::FAILURE;
        }

        $this->messagePreparator->ensureSubscribersHaveUuid($output);
        $this->messagePreparator->ensureCampaignsHaveUuid($output);
        $this->entityManager->flush();

        $campaigns = $this->findDueCampaigns($campaignId);
        if ($campaignId !== null && $campaigns === []) {
            $output->writeln(
                $this->translator->trans(
                    'Campaign %id% is not submitted or its embargo has not passed.',
                    ['%id%' => $campaignId]
                )
            );

            return Command::FAILURE;
        }

        $failed = $this->dispatchAll($campaigns, $output);
        $output->writeln(
            $this->translator->trans(
                'Finished, processed %count% campaigns.',
                ['%count%' => count($campaigns) - $failed]
            )
        );

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function findDueCampaigns(?int $campaignId): array
    {
        $campaigns = $this->messageRepository->getByStatusAndEmbargo(
            status: MessageStatus::Submitted,
            embargo: new DateTimeImmutable()
        );
        if ($campaignId === null) {
            return $campaigns;
        }

        return array_values(
            array_filter($campaigns, static fn ($campaign): bool => $campaign->getId() === $campaignId)
        );
    }

    /**
     * @param array<object> $campaigns
     * @return int number of campaigns that could not be dispatched
     */
    private function dispatchAll(array $campaigns, OutputInterface $output): int
    {
        $failed = 0;
        foreach ($campaigns as $campaign) {
            $id = $campaign->getId();
            $output->writeln($this->translator->trans('Sending campaign %id%', ['%id%' => $id]));
            try {
                $this->messageBus->dispatch(new SyncCampaignProcessorMessage(messageId: $id));
            } catch (Throwable $throwable) {
                ++$failed;
                $output->writeln($throwable->getMessage());
            }
        }

        return $failed;
    }
}
