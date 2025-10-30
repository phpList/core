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
