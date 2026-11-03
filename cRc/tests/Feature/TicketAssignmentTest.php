<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Milestone 3: creating tickets, admin-only assign/reassign, history and the "new assignment" alert. */
class TicketAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->alice = User::factory()->agent()->create(['full_name' => 'Alice Agent']);
        $this->bob = User::factory()->agent()->create(['full_name' => 'Bob Agent']);
    }

    private function payload(Contact $contact, array $over = []): array
    {
        return array_merge([
            'contact_id' => $contact->contact_id,
            'topic' => 'bug',
            'priority' => 'high',
            'title' => 'Invoice total is wrong',
            'description' => 'Tax missing',
        ], $over);
    }

    // ---- creating ---------------------------------------------------------

    public function test_admin_creates_a_ticket_assigned_to_an_agent(): void
    {
        $contact = Contact::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('tickets.store'), $this->payload($contact, ['assigned_to' => $this->alice->user_id]))
            ->assertRedirect();

        $ticket = Ticket::firstOrFail();
        $this->assertSame(sprintf('TKT-%04d', $ticket->ticket_id), $ticket->ticket_no);
        $this->assertSame($contact->company_id, $ticket->company_id, 'company is taken from the contact');
        $this->assertSame($this->alice->user_id, $ticket->assigned_to);
        $this->assertSame('open', $ticket->status);

        $this->assertDatabaseHas('ticket_assignments', [
            'ticket_id' => $ticket->ticket_id, 'from_user' => null,
            'to_user' => $this->alice->user_id, 'assigned_by' => $this->admin->user_id,
        ]);
        $this->assertCount(1, $this->alice->notifications);
        $this->assertSame($ticket->ticket_no, $this->alice->notifications->first()->data['ticket_no']);
    }

    public function test_admin_can_create_an_unassigned_ticket(): void
    {
        $this->actingAs($this->admin)->post(route('tickets.store'), $this->payload(Contact::factory()->create()))
            ->assertRedirect();

        $ticket = Ticket::firstOrFail();
        $this->assertNull($ticket->assigned_to);
        $this->assertDatabaseCount('ticket_assignments', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_agent_created_ticket_is_assigned_to_themselves_whatever_they_post(): void
    {
        $this->actingAs($this->alice)
            ->post(route('tickets.store'), $this->payload(Contact::factory()->create(), ['assigned_to' => $this->bob->user_id]))
            ->assertRedirect();

        $ticket = Ticket::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($this->alice->user_id, $ticket->assigned_to);
        $this->assertDatabaseCount('notifications', 0);   // no alert for assigning yourself
        $this->assertDatabaseHas('ticket_assignments', ['ticket_id' => $ticket->ticket_id, 'to_user' => $this->alice->user_id]);
    }

    public function test_ticket_creation_is_validated(): void
    {
        $contact = Contact::factory()->create();
        $inactiveContact = Contact::factory()->inactive()->create();
        $inactiveAgent = User::factory()->agent()->inactive()->create();
        $this->actingAs($this->admin);

        $this->post(route('tickets.store'), $this->payload($contact, ['contact_id' => 99999]))->assertSessionHasErrors('contact_id');
        $this->post(route('tickets.store'), $this->payload($inactiveContact))->assertSessionHasErrors('contact_id');
        $this->post(route('tickets.store'), $this->payload($contact, ['topic' => 'rant']))->assertSessionHasErrors('topic');
        $this->post(route('tickets.store'), $this->payload($contact, ['priority' => 'urgent']))->assertSessionHasErrors('priority');
        $this->post(route('tickets.store'), $this->payload($contact, ['title' => '']))->assertSessionHasErrors('title');
        $this->post(route('tickets.store'), $this->payload($contact, ['assigned_to' => $inactiveAgent->user_id]))->assertSessionHasErrors('assigned_to');
        $this->post(route('tickets.store'), $this->payload($contact, ['assigned_to' => $this->admin->user_id]))->assertSessionHasErrors('assigned_to');

        $this->assertDatabaseCount('tickets', 0);
    }

    // ---- assigning --------------------------------------------------------

    public function test_admin_assigns_a_queue_ticket_and_the_agent_is_alerted(): void
    {
        $ticket = Ticket::factory()->unassigned()->create();

        $this->actingAs($this->admin)
            ->post(route('tickets.assign', $ticket), ['assigned_to' => $this->alice->user_id, 'reason' => 'Knows this client'])
            ->assertRedirect(route('tickets.show', $ticket))
            ->assertSessionHas('status');

        $this->assertSame($this->alice->user_id, $ticket->fresh()->assigned_to);
        $this->assertDatabaseHas('ticket_assignments', [
            'ticket_id' => $ticket->ticket_id, 'from_user' => null, 'to_user' => $this->alice->user_id,
            'assigned_by' => $this->admin->user_id, 'reason' => 'Knows this client',
        ]);
        $this->assertSame('Knows this client', $this->alice->notifications()->first()->data['reason']);
        $this->assertSame(TicketAssigned::class, $this->alice->notifications()->first()->type);
    }

    public function test_reassignment_moves_access_and_keeps_the_history(): void
    {
        $ticket = Ticket::factory()->assignedTo($this->alice)->create(['title' => 'Moving ticket']);

        $this->actingAs($this->admin)
            ->post(route('tickets.assign', $ticket), ['assigned_to' => $this->bob->user_id, 'reason' => 'Alice on leave']);

        $ticket->refresh();
        $this->assertSame($this->bob->user_id, $ticket->assigned_to);
        $this->assertDatabaseHas('ticket_assignments', [
            'ticket_id' => $ticket->ticket_id, 'from_user' => $this->alice->user_id, 'to_user' => $this->bob->user_id,
        ]);
        $this->assertCount(1, $this->bob->notifications);
        $this->assertCount(0, $this->alice->notifications, 'the old agent is not alerted');

        // Old agent loses access (list and URL); new agent gains it.
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->alice);
        $this->get(route('tickets.show', $ticket))->assertNotFound();
        $this->get(route('tickets.index'))->assertDontSee('Moving ticket');

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->bob);
        $this->get(route('tickets.show', $ticket))->assertOk()->assertSee('Alice on leave');
        $this->get(route('tickets.index'))->assertSee('Moving ticket');

        // The record of who handled it is permanent (admin view).
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->admin);
        $this->get(route('tickets.show', $ticket))->assertSee('Alice Agent')->assertSee('Bob Agent');
        $this->assertSame(1, $ticket->assignments()->count());
    }

    public function test_every_reassignment_adds_a_history_row(): void
    {
        $ticket = Ticket::factory()->unassigned()->create();
        $this->actingAs($this->admin);

        foreach ([$this->alice, $this->bob, $this->alice] as $agent) {
            $this->post(route('tickets.assign', $ticket), ['assigned_to' => $agent->user_id]);
        }

        $rows = $ticket->assignments()->orderBy('assignment_id')->get();
        $this->assertCount(3, $rows);
        $this->assertSame([null, $this->alice->user_id, $this->bob->user_id], $rows->pluck('from_user')->all());
        $this->assertSame([$this->alice->user_id, $this->bob->user_id, $this->alice->user_id], $rows->pluck('to_user')->all());
    }

    public function test_assigning_to_the_current_agent_is_rejected_without_noise(): void
    {
        $ticket = Ticket::factory()->assignedTo($this->alice)->create();

        $this->actingAs($this->admin)->from(route('tickets.show', $ticket))
            ->post(route('tickets.assign', $ticket), ['assigned_to' => $this->alice->user_id])
            ->assertSessionHasErrors('assigned_to');

        $this->assertDatabaseCount('ticket_assignments', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_only_active_agents_can_receive_a_ticket(): void
    {
        $ticket = Ticket::factory()->unassigned()->create();
        $inactive = User::factory()->agent()->inactive()->create();
        $this->actingAs($this->admin);

        $this->post(route('tickets.assign', $ticket), ['assigned_to' => $inactive->user_id])->assertSessionHasErrors('assigned_to');
        $this->post(route('tickets.assign', $ticket), ['assigned_to' => $this->admin->user_id])->assertSessionHasErrors('assigned_to');
        $this->post(route('tickets.assign', $ticket), ['assigned_to' => 99999])->assertSessionHasErrors('assigned_to');
        $this->post(route('tickets.assign', $ticket), [])->assertSessionHasErrors('assigned_to');

        $this->assertNull($ticket->fresh()->assigned_to);
    }

    public function test_agents_cannot_assign_or_reassign_even_their_own_tickets(): void
    {
        $mine = Ticket::factory()->assignedTo($this->alice)->create();
        $queue = Ticket::factory()->unassigned()->create();
        $this->actingAs($this->alice);

        $this->post(route('tickets.assign', $mine), ['assigned_to' => $this->bob->user_id])->assertForbidden();
        // A queue ticket is invisible to agents, so they cannot even reach the assign route (404).
        $this->post(route('tickets.assign', $queue), ['assigned_to' => $this->alice->user_id])->assertNotFound();

        $this->assertSame($this->alice->user_id, $mine->fresh()->assigned_to);
        $this->assertNull($queue->fresh()->assigned_to);
        $this->assertDatabaseCount('ticket_assignments', 0);
    }

    public function test_agents_do_not_see_the_assign_form_but_admins_do(): void
    {
        $ticket = Ticket::factory()->assignedTo($this->alice)->create();

        $this->actingAs($this->alice)->get(route('tickets.show', $ticket))
            ->assertOk()->assertDontSee('Reassign')->assertDontSee('Select an agent');

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->admin)->get(route('tickets.show', $ticket))
            ->assertOk()->assertSee('Reassign')->assertSee('Select an agent');
    }

    public function test_unassigned_queue_filter_is_for_admins(): void
    {
        Ticket::factory()->unassigned()->create(['title' => 'Waiting in queue']);
        Ticket::factory()->assignedTo($this->alice)->create(['title' => 'Already owned']);

        $this->actingAs($this->admin)->get(route('tickets.index', ['assigned' => 'unassigned']))
            ->assertSee('Waiting in queue')->assertDontSee('Already owned');
        $this->get(route('tickets.index', ['assigned' => $this->alice->user_id]))
            ->assertSee('Already owned')->assertDontSee('Waiting in queue');
    }

    // ---- notifications ----------------------------------------------------

    public function test_agent_sees_new_assignments_and_can_mark_them_read(): void
    {
        $ticket = Ticket::factory()->unassigned()->create(['title' => 'Alert me please']);
        $this->actingAs($this->admin)->post(route('tickets.assign', $ticket), ['assigned_to' => $this->alice->user_id]);
        $this->actingAs($this->admin)->post(route('tickets.assign', Ticket::factory()->unassigned()->create()), ['assigned_to' => $this->bob->user_id]);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->alice)->get(route('agent.dashboard'))
            ->assertOk()->assertSee('Alert me please')->assertSee('Mark all as read');

        $this->post(route('notifications.read'))->assertRedirect();
        $this->assertCount(0, $this->alice->fresh()->unreadNotifications);
        $this->assertCount(1, $this->bob->fresh()->unreadNotifications, 'other users are untouched');
    }
}
