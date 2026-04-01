<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Command;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Identity\Model\Dto\CreateAdministratorDto;
use PhpList\Core\Domain\Identity\Model\PrivilegeFlag;
use PhpList\Core\Domain\Identity\Repository\AdministratorRepository;
use PhpList\Core\Domain\Identity\Service\Manager\AdministratorManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

#[AsCommand(
    name: 'phplist:defaults:import',
    description: 'Imports default values into the database (e.g., default admin with all privileges).'
)]
class ImportDefaultsCommand extends Command
{
    private const DEFAULT_LOGIN = 'admin';
    private const DEFAULT_EMAIL = 'admin@example.com';

    public function __construct(
        private readonly AdministratorRepository $administratorRepository,
        private readonly AdministratorManager $administratorManager,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $login = self::DEFAULT_LOGIN;
        $email = self::DEFAULT_EMAIL;
        $envPassword = getenv('PHPLIST_ADMIN_PASSWORD');
        $envPassword = is_string($envPassword) && trim($envPassword) !== '' ? $envPassword : null;

        $allPrivileges = $this->allPrivilegesGranted();

        $existing = $this->administratorRepository->findOneBy(['loginName' => $login]);
        if ($existing === null) {
            // If creating the default admin, require a password. Prefer env var, else prompt for input.
            $password = $envPassword;
            if ($password === null) {
                /** @var QuestionHelper $helper */
                $helper = $this->getHelper('question');
                $question = new Question('Enter password for default admin (login "admin"): ');
                $question->setHidden(true);
                $question->setHiddenFallback(false);
                $password = (string) $helper->ask($input, $output, $question);
                if (trim($password) === '') {
                    $output->writeln('<error>Password must not be empty.</error>');
                    return Command::FAILURE;
                }
            }

            $dto = new CreateAdministratorDto(
                loginName: $login,
                password: $password,
                email: $email,
                isSuperUser: true,
                privileges: $allPrivileges,
            );
            $admin = $this->administratorManager->createAdministrator($dto);
            $this->entityManager->flush();

            $output->writeln(sprintf(
                'Default admin created: login="%s", email="%s", superuser=yes, privileges=all',
                $admin->getLoginName(),
                $admin->getEmail()
            ));
        } else {
            $output->writeln(sprintf(
                'Default admin already exists: login="%s", email="%s"',
                $existing->getLoginName(),
                $existing->getEmail(),
            ));
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<string,bool>
     */
    private function allPrivilegesGranted(): array
    {
        $all = [];
        foreach (PrivilegeFlag::cases() as $flag) {
            $all[$flag->value] = true;
        }
        return $all;
    }
}
