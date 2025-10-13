<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Command;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Message\CampaignProcessorMessage;
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
    private MessageRepository $messageRepository;
    private LockFactory $lockFactory;
    private MessageProcessingPreparator $messagePreparator;
    private MessageBusInterface $messageBus;
    private ConfigProvider $configProvider;
    private TranslatorInterface $translator;
    private EntityManagerInterface $entityManager;

    public function __construct(
        MessageRepository $messageRepository,
        LockFactory $lockFactory,
        MessageProcessingPreparator $messagePreparator,
        MessageBusInterface $messageBus,
        ConfigProvider $configProvider,
        TranslatorInterface $translator,
        EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
        $this->messageRepository = $messageRepository;
        $this->lockFactory = $lockFactory;
        $this->messagePreparator = $messagePreparator;
        $this->messageBus = $messageBus;
        $this->configProvider = $configProvider;
        $this->translator = $translator;
        $this->entityManager = $entityManager;
    }

    /**
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
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
                $message = new CampaignProcessorMessage(messageId: $campaign->getId());
                $this->messageBus->dispatch($message);
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
