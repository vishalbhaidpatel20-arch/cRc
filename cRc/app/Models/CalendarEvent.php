<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One planned item on a user's own calendar. start_at is UTC; show it in the viewer's timezone (App\Support\UserTime). */
class CalendarEvent extends Model
{
    /** Minutes before the start that a reminder can fire (0 = at the time). */
    public const REMINDERS = [0, 5, 10, 15, 30, 60, 120, 1440];

    protected $table = 'calendar_events';

    protected $primaryKey = 'event_id';

    public $timestamps = false;

    protected $fillable = ['user_id', 'title', 'notes', 'start_at', 'all_day', 'remind_minutes', 'reminded_at', 'status'];

    protected function casts(): array
    {
        return ['start_at' => 'datetime', 'reminded_at' => 'datetime', 'all_day' => 'boolean', 'remind_minutes' => 'integer'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function getRouteKeyName(): string
    {
        return 'event_id';
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    /** "At the time", "15 minutes before", "1 day before". */
    public static function reminderLabel(?int $minutes): string
    {
        return match (true) {
            $minutes === null => 'No reminder',
            $minutes === 0 => 'At the time',
            $minutes === 1440 => '1 day before',
            $minutes >= 60 && $minutes % 60 === 0 => ($minutes / 60).' '.($minutes === 60 ? 'hour' : 'hours').' before',
            default => $minutes.' minutes before',
        };
    }
}
