<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Command;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Message\CampaignProcessor\CampaignProcessorMessage;
use PhpList\Core\Domain\Messaging\Model\Message\MessageStatus;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Service\MessageProcessingPreparator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

#[AsCommand(
    name: 'phplist:process-queue',
    description: 'Processes the email campaign queue.'
)]
class ProcessQueueCommand extends Command
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $lock = $this->lockFactory->createLock('queue_processor');
        if (!$lock->acquire()) {
            $output->writeln($this->translator->trans('Queue is already being processed by another instance.'));

            return Command::FAILURE;
        }

        if ($this->configProvider->isEnabled(ConfigOption::MaintenanceMode)) {
            $output->writeln(
                $this->translator->trans('The system is in maintenance mode, stopping. Try again later.')
            );

            return Command::FAILURE;
        }

        try {
            $this->messagePreparator->ensureSubscribersHaveUuid($output);
            $this->messagePreparator->ensureCampaignsHaveUuid($output);

            $this->entityManager->flush();
        } catch (Throwable $throwable) {
            $output->writeln($throwable->getMessage());
            $lock->release();

            return Command::FAILURE;
        }

        $campaigns = $this->messageRepository->getByStatusAndEmbargo(
            status: MessageStatus::Submitted,
            embargo: new DateTimeImmutable()
        );

        try {
            foreach ($campaigns as $campaign) {
                $this->messageBus->dispatch(new CampaignProcessorMessage(messageId: $campaign->getId()));
            }
        } catch (Throwable $throwable) {
            $output->writeln($throwable->getMessage());

            return Command::FAILURE;
        } finally {
            $lock->release();
        }

        return Command::SUCCESS;
    }
}
