<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Command;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Messaging\Service\SubscriberBlacklistService;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberBlacklistManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Equivalent of phplist3's `phplist -p blacklistemail -e email [-u uniqid] [-d date]`.
 *
 * Looks the subscriber up by unique id or email. A known subscriber is blacklisted through
 * SubscriberBlacklistService (flag, blacklist entry, history); an unknown address only gets a blacklist
 * entry. Already blacklisted addresses are treated as success, as in legacy. Prints "OK <email>" or
 * "FAIL" so existing scripts that parse the legacy output keep working.
 *
 * Deviation: the email is validated, and an unparsable --date is rejected instead of written to SQL.
 */
#[AsCommand(
    name: 'phplist:blacklist-email',
    description: 'Blacklists an email address (and its subscriber, if any).'
)]
class BlacklistEmailCommand extends Command
{
    private const REASON = 'blacklisted due to spam complaints';

    public function __construct(
        private readonly SubscriberRepository $subscriberRepository,
        private readonly SubscriberBlacklistManager $blacklistManager,
        private readonly SubscriberBlacklistService $blacklistService,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', 'e', InputOption::VALUE_REQUIRED, 'Email address to blacklist')
            ->addOption('uid', 'u', InputOption::VALUE_REQUIRED, 'Unique id of the subscriber')
            ->addOption('date', 'd', InputOption::VALUE_REQUIRED, 'Date the address was blacklisted (default: now)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = trim((string) $input->getOption('email'));
        $uid = trim((string) $input->getOption('uid'));
        $date = $this->parseDate($input->getOption('date'));

        if ($date === false || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            $output->writeln($this->translator->trans('Invalid email or date.'));

            return Command::INVALID;
        }

        $subscriber = $this->findSubscriber($uid, $email);
        $address = $subscriber?->getEmail() ?? $email;
        if ($address === '') {
            $output->writeln('FAIL');

            return Command::FAILURE;
        }

        try {
            $this->blacklist($subscriber, $address, $date);
        } catch (Throwable $throwable) {
            $output->writeln('FAIL');
            $output->writeln($throwable->getMessage());

            return Command::FAILURE;
        }

        $output->writeln('OK ' . $address);

        return Command::SUCCESS;
    }

    private function findSubscriber(string $uid, string $email): ?Subscriber
    {
        $subscriber = $uid !== '' ? $this->subscriberRepository->findOneByUniqueId($uid) : null;

        return $subscriber ?? ($email !== '' ? $this->subscriberRepository->findOneByEmail($email) : null);
    }

    private function blacklist(?Subscriber $subscriber, string $address, ?DateTime $date): void
    {
        if ($this->blacklistManager->isEmailBlacklisted($address)) {
            // legacy does this anyway, just to be sure
            $subscriber?->setBlacklisted(true);
            $this->entityManager->flush();

            return;
        }

        if ($subscriber !== null) {
            $this->blacklistService->blacklist($subscriber, self::REASON);
        } else {
            $this->blacklistManager->addEmailToBlacklist($address, self::REASON);
            $this->entityManager->flush();
        }

        if ($date !== null) {
            $this->blacklistManager->getBlacklistInfo($address)?->setAdded($date);
            $this->entityManager->flush();
        }
    }

    /** @return DateTime|false|null false when the value cannot be parsed */
    private function parseDate(mixed $value): DateTime|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new DateTime((string) $value);
        } catch (Throwable) {
            return false;
        }
    }
}
