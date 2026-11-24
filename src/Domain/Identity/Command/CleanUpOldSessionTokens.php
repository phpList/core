<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Command;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Identity\Repository\AdministratorTokenRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * TODO: housekeeping. This is the only cleanup command in core; phplist3 does other housekeeping inside web
 *  pages, and core has no command for it yet. Decide for each whether it is needed, then add commands (or one
 *  `phplist:housekeeping` command that runs them all, so a single cron entry is enough):
 *  - eventlog rows older than a retention period (legacy: the "delete entries older than 2 months" action on
 *    the eventlog page, eventlog.php; EventLogManager can only delete single entries)
 *  - unidentified bounces older than 2 months (legacy: bounces.php); not checked whether bounce processing
 *    here already prunes them
 *  - other expired tokens, if any (e.g. password reset tokens); not checked whether they are cleaned up
 *    elsewhere in core
 *  user_message_view rows: legacy only deletes them together with their message or subscriber (lib.php,
 *  inc/userlib.php) and never prunes them by age, so an age based cleanup would be new behaviour, not a port.
 *  Also add the new commands to the scheduling TODO on ProcessQueueCommand.
 */
#[AsCommand(
    name: 'phplist:clean-up-old-session-tokens',
    description: 'Removes expired administrator session tokens.'
)]
class CleanUpOldSessionTokens extends Command
{
    private AdministratorTokenRepository $tokenRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(AdministratorTokenRepository $tokenRepository, EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->tokenRepository = $tokenRepository;
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $expiredTokens = $this->tokenRepository->getExpired();

            $deletedCount = 0;

            foreach ($expiredTokens as $token) {
                $this->entityManager->remove($token);
                $deletedCount++;
            }

            $this->entityManager->flush();

            $output->writeln(sprintf('Successfully removed %d expired session token(s).', $deletedCount));
        } catch (Throwable $throwable) {
            $output->writeln(sprintf('Error removing expired session tokens: %s', $throwable->getMessage()));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
