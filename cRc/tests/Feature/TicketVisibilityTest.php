<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/** Milestone 3: an agent sees only the tickets assigned to them; admins see everything. */
class TicketVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $alice;

    private User $bob;

    private Ticket $aliceTicket;

    private Ticket $bobTicket;

    private Ticket $queueTicket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->alice = User::factory()->agent()->create(['full_name' => 'Alice Agent']);
        $this->bob = User::factory()->agent()->create(['full_name' => 'Bob Agent']);

        $this->aliceTicket = Ticket::factory()->assignedTo($this->alice)->create(['title' => 'Alice invoice problem']);
        $this->bobTicket = Ticket::factory()->assignedTo($this->bob)->create(['title' => 'Bob printer problem']);
        $this->queueTicket = Ticket::factory()->unassigned()->create(['title' => 'Nobody owns this problem']);
    }

    public function test_agent_list_contains_only_their_own_tickets(): void
    {
        $this->actingAs($this->alice)->get(route('tickets.index'))
            ->assertOk()
            ->assertSee('Alice invoice problem')
            ->assertDontSee('Bob printer problem')
            ->assertDontSee('Nobody owns this problem');
    }

    public function test_admin_sees_every_ticket(): void
    {
        $this->actingAs($this->admin)->get(route('tickets.index'))
            ->assertOk()
            ->assertSee('Alice invoice problem')
            ->assertSee('Bob printer problem')
            ->assertSee('Nobody owns this problem');
    }

    public function test_agent_cannot_open_another_agents_ticket_by_url(): void
    {
        $this->actingAs($this->alice);

        $this->get(route('tickets.show', $this->bobTicket))->assertNotFound();
        $this->get(route('tickets.edit', $this->bobTicket))->assertNotFound();
        $this->put(route('tickets.update', $this->bobTicket), $this->updatePayload(['title' => 'Hacked']))->assertNotFound();
        $this->assertSame('Bob printer problem', $this->bobTicket->fresh()->title);
    }

    public function test_agent_cannot_open_an_unassigned_ticket_by_url(): void
    {
        $this->actingAs($this->alice)->get(route('tickets.show', $this->queueTicket))->assertNotFound();
    }

    public function test_agent_can_open_and_update_their_own_ticket(): void
    {
        $this->actingAs($this->alice)->get(route('tickets.show', $this->aliceTicket))
            ->assertOk()->assertSee('Alice invoice problem');

        $this->put(route('tickets.update', $this->aliceTicket), $this->updatePayload([
            'title' => 'Alice retitled', 'status' => 'in_progress', 'priority' => 'critical',
        ]))->assertRedirect(route('tickets.show', $this->aliceTicket));

        $fresh = $this->aliceTicket->fresh();
        $this->assertSame('Alice retitled', $fresh->title);
        $this->assertSame('in_progress', $fresh->status);
        $this->assertSame('critical', $fresh->priority);
    }

    public function test_update_cannot_change_company_contact_or_assignee(): void
    {
        $otherContact = Contact::factory()->create();
        $originalContact = $this->aliceTicket->contact_id;
        $originalCompany = $this->aliceTicket->company_id;

        $this->actingAs($this->alice)->put(route('tickets.update', $this->aliceTicket), $this->updatePayload([
            'assigned_to' => $this->bob->user_id,
            'contact_id' => $otherContact->contact_id,
            'company_id' => $otherContact->company_id,
        ]));

        $fresh = $this->aliceTicket->fresh();
        $this->assertSame($this->alice->user_id, $fresh->assigned_to);
        $this->assertSame($originalContact, $fresh->contact_id);
        $this->assertSame($originalCompany, $fresh->company_id);
    }

    public function test_search_and_filters_never_leak_other_agents_tickets(): void
    {
        $this->actingAs($this->alice);

        $this->get(route('tickets.index', ['q' => 'printer']))->assertDontSee('Bob printer problem');
        $this->get(route('tickets.index', ['company_id' => $this->bobTicket->company_id]))->assertDontSee('Bob printer problem');
        $this->get(route('tickets.index', ['status' => 'all', 'priority' => 'medium']))->assertDontSee('Bob printer problem');
        // The assignee filter is admin only: for an agent it is ignored, not honoured.
        $this->get(route('tickets.index', ['assigned' => $this->bob->user_id]))->assertDontSee('Bob printer problem');
        $this->get(route('tickets.index', ['assigned' => 'unassigned']))->assertDontSee('Nobody owns this problem');
    }

    public function test_global_scope_filters_queries_for_agents_but_not_for_admins_or_guests(): void
    {
        $this->assertSame(3, Ticket::count(), 'no one logged in (console/seeders) is not filtered');

        $this->actingAs($this->alice);
        $this->assertSame(1, Ticket::count());
        $this->assertSame([$this->aliceTicket->ticket_id], Ticket::pluck('ticket_id')->all());
        $this->assertNull(Ticket::find($this->bobTicket->ticket_id));
        $this->assertSame(3, Ticket::withoutGlobalScopes()->count());

        $this->actingAs($this->admin);
        $this->assertSame(3, Ticket::count());
    }

    public function test_policy_decisions(): void
    {
        $this->assertTrue(Gate::forUser($this->admin)->allows('view', $this->bobTicket));
        $this->assertTrue(Gate::forUser($this->admin)->allows('update', $this->bobTicket));
        $this->assertTrue(Gate::forUser($this->admin)->allows('assign', $this->bobTicket));

        $this->assertTrue(Gate::forUser($this->bob)->allows('view', $this->bobTicket));
        $this->assertTrue(Gate::forUser($this->bob)->allows('update', $this->bobTicket));
        $this->assertFalse(Gate::forUser($this->bob)->allows('assign', $this->bobTicket), 'agents never assign');

        $this->assertFalse(Gate::forUser($this->alice)->allows('view', $this->bobTicket));
        $this->assertFalse(Gate::forUser($this->alice)->allows('update', $this->bobTicket));
        $this->assertFalse(Gate::forUser($this->alice)->allows('view', $this->queueTicket));
    }

    public function test_agent_dashboard_counts_only_their_tickets(): void
    {
        Ticket::factory()->assignedTo($this->alice)->priority('critical')->create();
        Ticket::factory()->assignedTo($this->alice)->resolved()->create();
        Ticket::factory()->assignedTo($this->bob)->priority('critical')->count(4)->create();

        $response = $this->actingAs($this->alice)->get(route('agent.dashboard'))->assertOk();

        $this->assertSame(2, $response->viewData('openCount'));
        $this->assertSame(1, (int) $response->viewData('byPriority')['critical']);
    }

    public function test_queue_order_is_priority_then_oldest_first(): void
    {
        Ticket::query()->delete();
        $low = Ticket::factory()->assignedTo($this->alice)->priority('low')->create(['title' => 'T-low', 'created_at' => now()->subDays(9)]);
        $critNew = Ticket::factory()->assignedTo($this->alice)->priority('critical')->create(['title' => 'T-crit-new', 'created_at' => now()->subDay()]);
        $critOld = Ticket::factory()->assignedTo($this->alice)->priority('critical')->create(['title' => 'T-crit-old', 'created_at' => now()->subDays(5)]);
        $high = Ticket::factory()->assignedTo($this->alice)->priority('high')->create(['title' => 'T-high', 'created_at' => now()->subDays(2)]);

        $ids = $this->actingAs($this->alice)->get(route('tickets.index'))->viewData('tickets')->pluck('ticket_id')->all();

        $this->assertSame([$critOld->ticket_id, $critNew->ticket_id, $high->ticket_id, $low->ticket_id], $ids);
    }

    public function test_resolved_tickets_are_hidden_by_default_but_reachable_by_filter(): void
    {
        Ticket::factory()->assignedTo($this->alice)->resolved()->create(['title' => 'Already fixed one']);
        $this->actingAs($this->alice);

        $this->get(route('tickets.index'))->assertDontSee('Already fixed one');
        $this->get(route('tickets.index', ['status' => 'resolved']))->assertSee('Already fixed one');
        $this->get(route('tickets.index', ['status' => 'all']))->assertSee('Already fixed one');
    }

    public function test_guests_cannot_reach_tickets(): void
    {
        $this->get(route('tickets.index'))->assertRedirect(route('login'));
        $this->get(route('tickets.show', $this->aliceTicket))->assertRedirect(route('login'));
    }

    private function updatePayload(array $over = []): array
    {
        return array_merge([
            'topic' => 'query', 'priority' => 'medium', 'status' => 'open',
            'title' => 'Some title', 'description' => 'Some description',
        ], $over);
    }
}
