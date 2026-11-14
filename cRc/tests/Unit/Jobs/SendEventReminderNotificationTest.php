<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendEventReminderNotification;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Notifications\EventReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendEventReminderNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function plan(User $owner): CalendarEvent
    {
        return CalendarEvent::create([
            'user_id' => $owner->user_id, 'title' => 'Call Bluewave', 'start_at' => now()->utc()->addMinutes(10),
            'all_day' => false, 'remind_minutes' => 15, 'status' => 'planned',
        ]);
    }

    public function test_it_notifies_the_owner(): void
    {
        Notification::fake();
        $alice = User::factory()->agent()->create();
        $other = User::factory()->agent()->create();
        $event = $this->plan($alice);

        (new SendEventReminderNotification($event->event_id))->handle();

        Notification::assertSentTo($alice, EventReminder::class);
        Notification::assertNotSentTo($other, EventReminder::class);
        Notification::assertCount(1);
    }

    public function test_it_skips_an_inactive_owner_and_a_missing_event(): void
    {
        Notification::fake();
        $event = $this->plan(User::factory()->agent()->inactive()->create());

        (new SendEventReminderNotification($event->event_id))->handle();
        (new SendEventReminderNotification(999999))->handle();

        Notification::assertNothingSent();
    }

    public function test_queue_settings(): void
    {
        $job = new SendEventReminderNotification(5);

        $this->assertSame('notifications', $job->queue);
        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 60, 300], $job->backoff());
        $this->assertSame('reminder:5', $job->uniqueId());
    }
}
