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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

#[AsCommand(
    name: 'phplist:create:admin',
    description: 'Creates a new admin user with all privileges.'
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly AdministratorRepository $administratorRepository,
        private readonly AdministratorManager $administratorManager,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('login', null, InputOption::VALUE_REQUIRED, 'Login name for the admin')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Password for the admin')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email for the admin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $login = $this->resolveValue($input, $output, $helper, 'login', 'Enter login for admin: ', false);
        if ($login === null) {
            $output->writeln('<error>Login must not be empty.</error>');
            return Command::FAILURE;
        }

        $existing = $this->administratorRepository->findOneBy(['loginName' => $login]);
        if ($existing !== null) {
            $output->writeln(sprintf(
                'Admin already exists: login="%s", email="%s"',
                $existing->getLoginName(),
                $existing->getEmail(),
            ));
            return Command::SUCCESS;
        }

        $email = $this->resolveValue($input, $output, $helper, 'email', 'Enter email for admin: ', false);
        if ($email === null) {
            $output->writeln('<error>Email must not be empty.</error>');
            return Command::FAILURE;
        }

        $password = $this->resolveValue(
            $input,
            $output,
            $helper,
            'password',
            sprintf('Enter password for admin (login "%s"): ', $login),
            true
        );
        if ($password === null) {
            $output->writeln('<error>Password must not be empty.</error>');
            return Command::FAILURE;
        }

        $dto = new CreateAdministratorDto(
            loginName: $login,
            password: $password,
            email: $email,
            isSuperUser: true,
            privileges: $this->allPrivilegesGranted(),
        );
        $admin = $this->administratorManager->createAdministrator($dto);
        $this->entityManager->flush();

        $output->writeln(sprintf(
            'Admin created: login="%s", email="%s", superuser=yes, privileges=all',
            $admin->getLoginName(),
            $admin->getEmail()
        ));

        return Command::SUCCESS;
    }

    private function resolveValue(
        InputInterface $input,
        OutputInterface $output,
        QuestionHelper $helper,
        string $optionName,
        string $prompt,
        bool $hidden
    ): ?string {
        $value = $input->getOption($optionName);

        if ($value === null) {
            $question = new Question($prompt);
            if ($hidden) {
                $question->setHidden(true);
                $question->setHiddenFallback(false);
            }
            $value = $helper->ask($input, $output, $question);
        }

        $value = (string) $value;
        return trim($value) === '' ? null : $value;
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
