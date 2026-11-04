<?php

namespace App\Notifications;

use App\Models\CalendarEvent;
use Illuminate\Notifications\Notification;

/** "Your plan starts soon": sent once when a calendar event's reminder time arrives (database channel, shows in the bell and as a toast). */
class EventReminder extends Notification
{
    public function __construct(public CalendarEvent $event) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'event_id' => $this->event->event_id,
            'title' => $this->event->title,
            'start_at' => $this->event->start_at->copy()->utc()->toIso8601String(),
            'all_day' => (bool) $this->event->all_day,
        ];
    }
}
