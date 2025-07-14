<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Command;

use PhpList\Core\Domain\Messaging\Service\CampaignProcessor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Service\MessageProcessingPreparator;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Console\Attribute\AsCommand;
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
    private CampaignProcessor $campaignProcessor;

    public function __construct(
        MessageRepository $messageRepository,
        LockFactory $lockFactory,
        MessageProcessingPreparator $messagePreparator,
        CampaignProcessor $campaignProcessor,
    ) {
        parent::__construct();
        $this->messageRepository = $messageRepository;
        $this->lockFactory = $lockFactory;
        $this->messagePreparator = $messagePreparator;
        $this->campaignProcessor = $campaignProcessor;
    }

    /**
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $lock = $this->lockFactory->createLock('queue_processor');
        if (!$lock->acquire()) {
            $output->writeln('Queue is already being processed by another instance.');

            return Command::FAILURE;
        }

        try {
            $this->messagePreparator->ensureSubscribersHaveUuid($output);
            $this->messagePreparator->ensureCampaignsHaveUuid($output);

            $campaigns = $this->messageRepository->findBy(['status' => 'submitted']);

            foreach ($campaigns as $campaign) {
                $this->campaignProcessor->process($campaign, $output);
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
