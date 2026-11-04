<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\User;
use App\Notifications\EventReminder;

/**
 * Sends the "your plan starts soon" notification for calendar events whose reminder time has arrived.
 *
 * Called from the bell's poll (so people get it within ~20 s while they work in the app) and from the scheduled command
 * `crc:send-reminders` (every minute, for people who are not in the app). Each event is notified at most once.
 */
class EventReminders
{
    /** A reminder more than this long after the event started is stale: it is marked sent without bothering anyone. */
    private const STALE_HOURS = 6;

    /** @return int how many notifications were sent */
    public function sendDue(?User $only = null): int
    {
        $sent = 0;
        $now = now()->utc();

        $due = CalendarEvent::query()
            ->where('status', 'planned')
            ->whereNotNull('remind_minutes')
            ->whereNull('reminded_at')
            ->whereRaw('DATE_SUB(start_at, INTERVAL remind_minutes MINUTE) <= ?', [$now->format('Y-m-d H:i:s')])
            ->when($only, fn ($q) => $q->where('user_id', $only->user_id))
            ->with('owner')
            ->orderBy('start_at')
            ->limit(200)
            ->get();

        foreach ($due as $event) {
            // Claim it first, so two overlapping polls (or the command and a poll) cannot both notify.
            $claimed = CalendarEvent::whereKey($event->event_id)->whereNull('reminded_at')->update(['reminded_at' => $now]);
            if (! $claimed) {
                continue;
            }

            if ($event->start_at->lt($now->subHours(self::STALE_HOURS)) || ! $event->owner?->is_active) {
                continue;
            }

            $event->owner->notify(new EventReminder($event));
            $sent++;
        }

        return $sent;
    }
}
