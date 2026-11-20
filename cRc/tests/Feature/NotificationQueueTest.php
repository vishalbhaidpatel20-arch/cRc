<?php

namespace Tests\Feature;

use App\Jobs\SendEventReminderNotification;
use App\Jobs\SendTicketAssignedNotification;
use App\Jobs\SendTicketCompletedNotification;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\User;
use App\Services\EventReminders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Notifications are not sent inside the request: the controllers and EventReminders only queue a job on "notifications". */
class NotificationQueueTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $alice;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->admin = User::factory()->admin()->create();
        $this->alice = User::factory()->agent()->create();
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user);
    }

    private function payload(Contact $contact, array $over = []): array
    {
        return array_merge([
            'contact_id' => $contact->contact_id, 'topic' => 'query', 'priority' => 'medium',
            'title' => 'Printer is down', 'description' => 'Details', 'due_at' => null,
        ], $over);
    }

    private function save(User $who, Ticket $ticket, string $status)
    {
        return $this->as($who)->put(route('tickets.update', $ticket), [
            'topic' => $ticket->topic, 'priority' => $ticket->priority, 'status' => $status,
            'title' => $ticket->title, 'description' => 'Details', 'due_at' => null,
        ]);
    }

    public function test_admin_assigning_dispatches_the_assigned_job_with_ids_only(): void
    {
        $ticket = Ticket::factory()->unassigned()->create();

        $this->as($this->admin)
            ->post(route('tickets.assign', $ticket), ['assigned_to' => $this->alice->user_id, 'reason' => 'Knows this client'])
            ->assertRedirect();

        Queue::assertPushedOn('notifications', SendTicketAssignedNotification::class, fn ($job) => $job->ticketId === $ticket->ticket_id
            && $job->agentId === $this->alice->user_id
            && $job->assignedById === $this->admin->user_id
            && $job->reason === 'Knows this client');
        Queue::assertPushed(SendTicketAssignedNotification::class, 1);
        $this->assertDatabaseCount('notifications', 0);   // nothing is sent until a worker runs the job
    }

    public function test_assigning_to_the_agent_who_already_has_it_dispatches_nothing(): void
    {
        $ticket = Ticket::factory()->assignedTo($this->alice)->create();

        $this->as($this->admin)
            ->post(route('tickets.assign', $ticket), ['assigned_to' => $this->alice->user_id])
            ->assertSessionHasErrors('assigned_to');

        Queue::assertNothingPushed();
    }

    public function test_admin_creating_a_ticket_for_an_agent_dispatches_the_assigned_job(): void
    {
        $this->as($this->admin)
            ->post(route('tickets.store'), $this->payload(Contact::factory()->create(), ['assigned_to' => $this->alice->user_id]))
            ->assertRedirect();

        $ticket = Ticket::withoutGlobalScopes()->firstOrFail();
        Queue::assertPushedOn('notifications', SendTicketAssignedNotification::class, fn ($job) => $job->ticketId === $ticket->ticket_id
            && $job->agentId === $this->alice->user_id
            && $job->assignedById === $this->admin->user_id
            && $job->reason === 'Assigned at creation');
    }

    public function test_an_agent_creating_their_own_ticket_dispatches_nothing(): void
    {
        $contact = Contact::factory()->create();
        $this->workAt($this->alice, $contact);   // she may only open tickets where she already works (adds 1 resolved ticket)
        $this->as($this->alice)->post(route('tickets.store'), $this->payload($contact))->assertRedirect();

        $this->assertDatabaseCount('tickets', 2);
        Queue::assertNothingPushed();
    }

    public function test_an_agent_resolving_their_ticket_dispatches_the_completed_job(): void
    {
        $ticket = Ticket::factory()->assignedTo($this->alice)->create();

        $this->save($this->alice, $ticket, 'resolved')->assertRedirect();

        Queue::assertPushedOn('notifications', SendTicketCompletedNotification::class, fn ($job) => $job->ticketId === $ticket->ticket_id
            && $job->agentId === $this->alice->user_id);
        Queue::assertPushed(SendTicketCompletedNotification::class, 1);
    }

    public function test_an_admin_resolving_a_ticket_dispatches_nothing(): void
    {
        $ticket = Ticket::factory()->assignedTo($this->alice)->create();

        $this->save($this->admin, $ticket, 'resolved')->assertRedirect();

        Queue::assertNothingPushed();
    }

    public function test_send_due_dispatches_one_reminder_job_per_due_event_and_nothing_the_second_time(): void
    {
        $due = [];
        foreach ([30, 20] as $minutesAgo) {
            $due[] = CalendarEvent::create([
                'user_id' => $this->alice->user_id, 'title' => "Plan {$minutesAgo}", 'start_at' => now()->utc()->addMinutes(5),
                'all_day' => false, 'remind_minutes' => $minutesAgo, 'status' => 'planned',
            ]);
        }
        CalendarEvent::create([   // reminder time is still in the future
            'user_id' => $this->alice->user_id, 'title' => 'Later', 'start_at' => now()->utc()->addHours(3),
            'all_day' => false, 'remind_minutes' => 10, 'status' => 'planned',
        ]);

        $this->assertSame(2, app(EventReminders::class)->sendDue());
        Queue::assertPushed(SendEventReminderNotification::class, 2);
        foreach ($due as $event) {
            Queue::assertPushedOn('notifications', SendEventReminderNotification::class, fn ($job) => $job->eventId === $event->event_id);
        }

        $this->assertSame(0, app(EventReminders::class)->sendDue());
        Queue::assertPushed(SendEventReminderNotification::class, 2);
    }
}
