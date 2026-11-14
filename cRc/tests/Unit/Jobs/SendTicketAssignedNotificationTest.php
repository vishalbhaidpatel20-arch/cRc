<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendTicketAssignedNotification;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendTicketAssignedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_notifies_the_agent_who_holds_the_ticket(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $alice = User::factory()->agent()->create();
        $ticket = Ticket::factory()->assignedTo($alice)->create();

        (new SendTicketAssignedNotification($ticket->ticket_id, $alice->user_id, $admin->user_id, 'Please take it'))->handle();

        Notification::assertSentTo($alice, TicketAssigned::class, fn ($n) => $n->ticket->ticket_id === $ticket->ticket_id
            && $n->assignedBy->user_id === $admin->user_id && $n->reason === 'Please take it');
        Notification::assertCount(1);
    }

    public function test_it_skips_when_the_ticket_was_reassigned_meanwhile(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $alice = User::factory()->agent()->create();
        $bob = User::factory()->agent()->create();
        $ticket = Ticket::factory()->assignedTo($bob)->create();

        (new SendTicketAssignedNotification($ticket->ticket_id, $alice->user_id, $admin->user_id, null))->handle();

        Notification::assertNothingSent();
    }

    public function test_it_skips_an_inactive_agent(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $alice = User::factory()->agent()->inactive()->create();
        $ticket = Ticket::factory()->assignedTo($alice)->create();

        (new SendTicketAssignedNotification($ticket->ticket_id, $alice->user_id, $admin->user_id, null))->handle();

        Notification::assertNothingSent();
    }

    public function test_it_skips_missing_records_and_works_without_a_signed_in_user(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $alice = User::factory()->agent()->create();

        (new SendTicketAssignedNotification(999999, $alice->user_id, $admin->user_id, null))->handle();
        Notification::assertNothingSent();

        // A worker has no signed-in user: the Ticket scope must not hide the ticket from the job.
        $this->assertGuest();
        $ticket = Ticket::factory()->assignedTo($alice)->create();
        (new SendTicketAssignedNotification($ticket->ticket_id, $alice->user_id, $admin->user_id, null))->handle();
        Notification::assertSentTo($alice, TicketAssigned::class);
    }

    public function test_queue_settings(): void
    {
        $job = new SendTicketAssignedNotification(5, 6, 7, null);

        $this->assertSame('notifications', $job->queue);
        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 60, 300], $job->backoff());
        $this->assertSame('assigned:5:6', $job->uniqueId());
    }
}
