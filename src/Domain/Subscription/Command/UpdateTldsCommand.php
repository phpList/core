<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Command;

use PhpList\Core\Domain\Subscription\Service\TldListService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Equivalent of phplist3's `phplist -p updatetlds [-f]`: refreshes the list of top level domains used
 * to validate email addresses (for example when importing subscribers). Meant for a monthly cron job.
 * Exits with a failure when the download failed, so cron can alert on it.
 */
#[AsCommand(
    name: 'phplist:update-tlds',
    description: 'Refreshes the list of top level domains used for email validation.'
)]
class UpdateTldsCommand extends Command
{
    public function __construct(
        private readonly TldListService $tldListService,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Refresh even if it was done in the last 30 days');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->tldListService->refresh((bool) $input->getOption('force'));

        if ($result->remoteFailed()) {
            $output->writeln($this->translator->trans('Could not download the list of top level domains.'));
        }
        $output->writeln($this->translator->trans(
            'Now we have %count% top level domains',
            ['%count%' => $result->count]
        ));

        return $result->remoteFailed() ? Command::FAILURE : Command::SUCCESS;
    }
}
