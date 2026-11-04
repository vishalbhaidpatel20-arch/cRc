<?php

namespace App\Console\Commands;

use App\Services\EventReminders;
use Illuminate\Console\Command;

/** Run every minute by the scheduler (routes/console.php) so reminders also reach people who are not in the app. */
class SendEventReminders extends Command
{
    protected $signature = 'crc:send-reminders';

    protected $description = 'Send the notifications for calendar events whose reminder time has arrived';

    public function handle(EventReminders $reminders): int
    {
        $this->info($reminders->sendDue().' reminder(s) sent.');

        return self::SUCCESS;
    }
}
