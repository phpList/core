<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Schedule;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Recurring console commands, consumed by `bin/console messenger:consume scheduler_default`.
 *
 * The frequencies are relative interval strings ("1 minute", "1 day", "1 month"); an empty value disables that entry.
 * Defaults follow phplist3's cron.php (queue every minute, bounces once a day).
 */
#[AsSchedule('default')]
class PhpListSchedule implements ScheduleProviderInterface
{
    public function __construct(
        private readonly LockFactory $lockFactory,
        private readonly string $queueInterval = '1 minute',
        private readonly string $bounceInterval = '1 day',
        private readonly string $tldInterval = '1 month',
        private readonly string $cleanupInterval = '1 day',
    ) {
    }

    public function getSchedule(): Schedule
    {
        $schedule = new Schedule();
        // the lock keeps several scheduler workers from firing the same entry; the commands keep their own locks
        $schedule->lock($this->lockFactory->createLock('phplist_schedule'));

        $jobs = [
            [$this->queueInterval, 'phplist:process-queue'],
            [$this->bounceInterval, 'phplist:bounces:process'],
            [$this->tldInterval, 'phplist:update-tlds'],
            [$this->cleanupInterval, 'phplist:clean-up-old-session-tokens'],
        ];

        foreach ($jobs as [$interval, $command]) {
            if (trim($interval) === '') {
                continue;
            }
            $schedule->add(RecurringMessage::every(trim($interval), new RunCommandMessage($command)));
        }

        return $schedule;
    }
}
