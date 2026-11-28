# Scheduling

Core ships a Symfony Scheduler schedule (`PhpList\Core\Domain\Common\Schedule\PhpListSchedule`)
that runs the recurring commands for you.

## Default: Scheduler worker

Run one long-lived worker (supervisor/systemd), next to the `async_email` consumer:

```bash
bin/console messenger:consume scheduler_default
```

| Command                                | Default   | Env variable                |
|----------------------------------------|-----------|-----------------------------|
| `phplist:process-queue`                | 1 minute  | `PHPLIST_SCHEDULE_QUEUE`    |
| `phplist:bounces:process`              | 1 day     | `PHPLIST_SCHEDULE_BOUNCES`  |
| `phplist:update-tlds`                  | 1 month   | `PHPLIST_SCHEDULE_TLDS`     |
| `phplist:clean-up-old-session-tokens`  | 1 day     | `PHPLIST_SCHEDULE_CLEANUP`  |

Values are relative interval strings (`"5 minutes"`, `"1 day"`); an empty value disables that entry. The
defaults are (queue every minute, bounces daily).

Overlapping runs are prevented: the schedule is locked, and `phplist:process-queue` /
`phplist:bounces:process` keep their own locks, so a slow run makes the next one exit instead of piling up.

## Alternative: system crontab

On hosts where a long-running worker is not possible, disable the scheduler and use cron:

```cron
* * * * * php /path/to/bin/console phplist:process-queue
0 3 * * * php /path/to/bin/console phplist:bounces:process
0 4 1 * * php /path/to/bin/console phplist:update-tlds
30 4 * * * php /path/to/bin/console phplist:clean-up-old-session-tokens
```

Running both is harmless because of the locks, but pointless.
