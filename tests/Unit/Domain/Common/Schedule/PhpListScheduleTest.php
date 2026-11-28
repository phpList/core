<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Common\Schedule;

use PhpList\Core\Domain\Common\Schedule\PhpListSchedule;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\RecurringMessage;

class PhpListScheduleTest extends TestCase
{
    /** @return list<string> */
    private function commands(PhpListSchedule $provider): array
    {
        $commands = [];
        /** @var RecurringMessage $recurring */
        foreach ($provider->getSchedule()->getRecurringMessages() as $recurring) {
            $now = new \DateTimeImmutable('2026-01-01 00:00:00');
            $context = new MessageContext('default', $recurring->getId(), $recurring->getTrigger(), $now);
            foreach ($recurring->getProvider()->getMessages($context) as $message) {
                self::assertInstanceOf(RunCommandMessage::class, $message);
                $commands[] = $message->input;
            }
        }

        return $commands;
    }

    public function testDefaultScheduleContainsAllRecurringCommands(): void
    {
        $provider = new PhpListSchedule(new LockFactory(new InMemoryStore()));

        self::assertSame(
            [
                'phplist:process-queue',
                'phplist:bounces:process',
                'phplist:update-tlds',
                'phplist:clean-up-old-session-tokens',
            ],
            $this->commands($provider)
        );
    }

    public function testEmptyIntervalDisablesEntry(): void
    {
        $provider = new PhpListSchedule(new LockFactory(new InMemoryStore()), bounceInterval: '');

        self::assertNotContains('phplist:bounces:process', $this->commands($provider));
        self::assertContains('phplist:process-queue', $this->commands($provider));
    }
}
