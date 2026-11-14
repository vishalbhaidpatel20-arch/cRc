<?php

namespace App\Jobs;

use App\Models\CalendarEvent;
use App\Notifications\EventReminder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Sends the "your plan starts soon" notification. EventReminders::sendDue() has already claimed the event (reminded_at). */
class SendEventReminderNotification implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 3;

    public function __construct(public int $eventId)
    {
        $this->onQueue('notifications');
    }

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function uniqueId(): string
    {
        return "reminder:{$this->eventId}";
    }

    public function handle(): void
    {
        $event = CalendarEvent::with('owner')->find($this->eventId);

        if (! $event || ! $event->owner || ! $event->owner->is_active) {
            return;
        }

        $event->owner->notify(new EventReminder($event));
    }

    public function failed(Throwable $e): void
    {
        Log::error('EventReminder notification failed', [
            'event_id' => $this->eventId,
            'error' => $e->getMessage(),
        ]);
    }
}
