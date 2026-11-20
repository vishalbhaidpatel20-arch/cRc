<?php

namespace Tests\Feature;

use App\Jobs\SendTicketCompletedNotification;
use App\Models\Interaction;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The "Log a conversation" form can also change the ticket's status (open / in progress / waiting / resolved).
 * Empty keeps it.
 */
class TicketStatusFromLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $alice;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->alice = User::factory()->agent()->create(['full_name' => 'Alice Agent']);
        $this->ticket = Ticket::factory()->assignedTo($this->alice)->create();
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user);
    }

    private function log(array $extra = []): array
    {
        return $extra + [
            'contact_id' => $this->ticket->contact_id, 'channel' => 'call', 'direction' => 'inbound',
            'topic' => 'query', 'summary' => 'Talked it through.',
        ];
    }

    private function ticketStatus(): string
    {
        return Ticket::withoutGlobalScopes()->find($this->ticket->ticket_id)->status;
    }

    public function test_the_ticket_page_log_form_offers_every_status(): void
    {
        $page = $this->as($this->alice)->get(route('tickets.show', $this->ticket))->assertOk()
            ->assertSee('name="ticket_status"', false)
            ->assertSee('Keep as Open');

        foreach (['In progress', 'Waiting', 'Resolved'] as $label) {
            $page->assertSee('>'.$label.'</option>', false);
        }
        $page->assertDontSee('Not resolved');
    }

    public function test_logging_from_the_ticket_page_changes_the_status(): void
    {
        $this->as($this->alice)->post(route('tickets.interactions.store', $this->ticket), $this->log(['ticket_status' => 'in_progress']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', "Conversation logged. {$this->ticket->ticket_no} is now In progress.");

        $this->assertSame('in_progress', $this->ticketStatus());
        $this->assertDatabaseHas('interactions', ['ticket_id' => $this->ticket->ticket_id, 'summary' => 'Talked it through.']);
    }

    public function test_an_empty_status_keeps_the_ticket_as_it_is(): void
    {
        $this->as($this->alice)->post(route('tickets.interactions.store', $this->ticket), $this->log(['ticket_status' => '']))
            ->assertSessionHasNoErrors()->assertSessionHas('status', 'Conversation logged.');

        $this->assertSame('open', $this->ticketStatus());
    }

    public function test_resolved_closes_the_ticket(): void
    {
        $this->as($this->alice)->post(route('tickets.interactions.store', $this->ticket), $this->log(['ticket_status' => 'resolved']))
            ->assertSessionHasNoErrors();

        $ticket = Ticket::withoutGlobalScopes()->find($this->ticket->ticket_id);
        $this->assertSame('resolved', $ticket->status);
        $this->assertTrue($ticket->isClosed());
        $this->assertSame(0, Ticket::withoutGlobalScopes()->open()->count());   // gone from open lists and counts
        $this->as($this->alice)->get(route('tickets.index'))->assertDontSee($ticket->title);
        $this->as($this->alice)->get(route('tickets.index', ['status' => 'resolved']))->assertSee($ticket->title);
    }

    public function test_an_unknown_status_is_rejected_and_nothing_is_saved(): void
    {
        $this->as($this->alice)->post(route('tickets.interactions.store', $this->ticket), $this->log(['ticket_status' => 'not_resolved']))
            ->assertSessionHasErrors('ticket_status');

        $this->assertSame('open', $this->ticketStatus());
        $this->assertSame(0, Interaction::count());
    }

    public function test_the_contact_page_changes_the_status_of_the_picked_ticket(): void
    {
        $this->as($this->alice)->post(route('contacts.interactions.store', $this->ticket->contact_id), $this->log([
            'ticket_id' => $this->ticket->ticket_id, 'ticket_status' => 'waiting',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('waiting', $this->ticketStatus());
    }

    public function test_the_contact_page_needs_a_ticket_to_change_a_status(): void
    {
        $this->as($this->alice)->post(route('contacts.interactions.store', $this->ticket->contact_id), $this->log(['ticket_status' => 'resolved']))
            ->assertSessionHasErrors('ticket_status');

        $this->assertSame('open', $this->ticketStatus());
        $this->assertSame(0, Interaction::count());
    }

    public function test_an_agent_cannot_change_another_agents_ticket(): void
    {
        $bob = User::factory()->agent()->create();

        // The ticket page is a 404 for Bob, and so is the contact page (he has no ticket at that company).
        $this->as($bob)->post(route('tickets.interactions.store', $this->ticket), $this->log(['ticket_status' => 'resolved']))->assertNotFound();
        $this->as($bob)->post(route('contacts.interactions.store', $this->ticket->contact_id), $this->log([
            'ticket_id' => $this->ticket->ticket_id, 'ticket_status' => 'resolved',
        ]))->assertNotFound();

        $this->assertSame('open', $this->ticketStatus());
    }

    public function test_an_admin_can_change_any_ticket_from_the_log(): void
    {
        $this->as($this->admin)->post(route('tickets.interactions.store', $this->ticket), $this->log(['ticket_status' => 'waiting']))
            ->assertSessionHasNoErrors();

        $this->assertSame('waiting', $this->ticketStatus());
    }

    public function test_the_conversations_page_shows_the_ticket_status_only_to_who_can_see_the_ticket(): void
    {
        $this->as($this->alice)->post(route('tickets.interactions.store', $this->ticket), $this->log(['ticket_status' => 'waiting']));
        $bob = User::factory()->agent()->create();

        $this->as($this->alice)->get(route('interactions.index'))->assertOk()
            ->assertSee('>Ticket status</th>', false)
            ->assertSee('<span class="pill st-waiting">Waiting</span>', false);
        $this->as($this->admin)->get(route('interactions.index'))
            ->assertSee('<span class="pill st-waiting">Waiting</span>', false);

        // Bob does not even see the conversation: it is on Alice's ticket.
        $this->as($bob)->get(route('interactions.index'))->assertOk()
            ->assertDontSee('Talked it through.')
            ->assertDontSee('pill st-waiting', false);
    }

    public function test_the_agent_resolving_from_the_log_tells_the_admin_but_other_statuses_do_not(): void
    {
        Queue::fake();

        $this->as($this->alice)->post(route('tickets.interactions.store', $this->ticket), $this->log(['ticket_status' => 'resolved']));
        Queue::assertPushed(SendTicketCompletedNotification::class, fn ($job) => $job->ticketId === $this->ticket->ticket_id);

        $other = Ticket::factory()->assignedTo($this->alice)->create();
        $this->as($this->alice)->post(route('tickets.interactions.store', $other), $this->log([
            'contact_id' => $other->contact_id, 'ticket_status' => 'waiting',
        ]));
        Queue::assertPushed(SendTicketCompletedNotification::class, 1);
    }
}
