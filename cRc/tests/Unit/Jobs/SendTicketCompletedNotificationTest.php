<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendTicketCompletedNotification;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\User;
use App\Notifications\TicketCompleted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendTicketCompletedNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function assignedBy(User $admin, User $agent): Ticket
    {
        $ticket = Ticket::factory()->assignedTo($agent)->resolved()->create();
        TicketAssignment::create([
            'ticket_id' => $ticket->ticket_id, 'from_user' => null, 'to_user' => $agent->user_id,
            'assigned_by' => $admin->user_id, 'reason' => null, 'assigned_at' => now(),
        ]);

        return $ticket;
    }

    public function test_it_notifies_only_the_admin_who_assigned_the_ticket(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $alice = User::factory()->agent()->create();
        $ticket = $this->assignedBy($admin, $alice);

        (new SendTicketCompletedNotification($ticket->ticket_id, $alice->user_id))->handle();

        Notification::assertSentTo($admin, TicketCompleted::class, fn ($n) => $n->ticket->ticket_id === $ticket->ticket_id
            && $n->completedBy->user_id === $alice->user_id);
        Notification::assertNotSentTo($other, TicketCompleted::class);
    }

    public function test_it_falls_back_to_all_active_admins_when_the_assigner_is_inactive(): void
    {
        Notification::fake();
        $gone = User::factory()->admin()->inactive()->create();
        $a1 = User::factory()->admin()->create();
        $a2 = User::factory()->admin()->create();
        $alice = User::factory()->agent()->create();
        $ticket = $this->assignedBy($gone, $alice);

        (new SendTicketCompletedNotification($ticket->ticket_id, $alice->user_id))->handle();

        Notification::assertSentTo([$a1, $a2], TicketCompleted::class);
        Notification::assertNotSentTo($gone, TicketCompleted::class);
    }

    public function test_it_falls_back_to_all_active_admins_when_the_agent_made_the_ticket_themselves(): void
    {
        Notification::fake();
        $a1 = User::factory()->admin()->create();
        $alice = User::factory()->agent()->create();
        $ticket = Ticket::factory()->assignedTo($alice)->resolved()->create();   // no assignment row

        (new SendTicketCompletedNotification($ticket->ticket_id, $alice->user_id))->handle();

        Notification::assertSentTo($a1, TicketCompleted::class);
    }

    public function test_it_skips_missing_records(): void
    {
        Notification::fake();
        User::factory()->admin()->create();
        $alice = User::factory()->agent()->create();

        (new SendTicketCompletedNotification(999999, $alice->user_id))->handle();

        Notification::assertNothingSent();
    }

    public function test_queue_settings(): void
    {
        $job = new SendTicketCompletedNotification(5, 6);

        $this->assertSame('notifications', $job->queue);
        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 60, 300], $job->backoff());
        $this->assertSame('completed:5', $job->uniqueId());
    }
}
