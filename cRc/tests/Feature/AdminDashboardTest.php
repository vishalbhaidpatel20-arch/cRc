<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\FollowUp;
use App\Models\Interaction;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Milestone 5: the admin dashboard (unassigned queue, workload per agent, summary cards). */
class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $anna;

    private User $ben;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['full_name' => 'Boss Admin']);
        $this->anna = User::factory()->agent()->create(['full_name' => 'Anna Agent']);
        $this->ben = User::factory()->agent()->create(['full_name' => 'Ben Agent']);
    }

    private function dash(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->get(route('admin.dashboard'));
    }

    private function row(\Illuminate\Support\Collection $workload, User $u): ?object
    {
        return $workload->firstWhere('user_id', $u->user_id);
    }

    // ---- access ----------------------------------------------------------

    public function test_only_admins_can_open_the_dashboard(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

        $this->actingAs($this->anna)->get(route('admin.dashboard'))->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Admin dashboard');
    }

    public function test_agents_never_see_team_data_on_their_own_dashboard(): void
    {
        Ticket::factory()->unassigned()->create(['title' => 'Queue secret title']);
        Ticket::factory()->assignedTo($this->ben)->create(['title' => 'Bens private ticket']);

        $this->actingAs($this->anna)->get(route('agent.dashboard'))
            ->assertOk()->assertDontSee('Queue secret title')->assertDontSee('Bens private ticket')
            ->assertDontSee('Workload per agent')->assertDontSee('Unassigned queue');
    }

    // ---- summary cards ---------------------------------------------------

    public function test_summary_cards_count_the_right_tickets(): void
    {
        Ticket::factory()->unassigned()->priority('critical')->create();                       // open, unassigned, critical
        Ticket::factory()->assignedTo($this->anna)->priority('critical')->create(['due_at' => now()->subDay()]); // open, critical, overdue
        Ticket::factory()->assignedTo($this->anna)->create(['status' => 'in_progress']);       // open
        Ticket::factory()->assignedTo($this->ben)->create(['status' => 'waiting']);            // open
        Ticket::factory()->assignedTo($this->ben)->resolved()->priority('critical')->create(['due_at' => now()->subDay()]); // resolved: counts nowhere
        Ticket::factory()->unassigned()->resolved()->create();                                 // resolved: not queue

        $cards = $this->dash()->viewData('cards');

        $this->assertSame(4, $cards['open']);
        $this->assertSame(1, $cards['unassigned']);
        $this->assertSame(2, $cards['critical']);
        $this->assertSame(1, $cards['overdue']);
    }

    public function test_conversations_today_and_overdue_follow_ups(): void
    {
        $contact = Contact::factory()->create();
        Interaction::factory()->create(['contact_id' => $contact->contact_id, 'interaction_at' => now()->subMinutes(5)]);
        Interaction::factory()->create(['contact_id' => $contact->contact_id, 'interaction_at' => now()->subDays(3)]);
        FollowUp::create(['contact_id' => $contact->contact_id, 'assigned_to' => $this->anna->user_id, 'due_date' => today()->subDay(), 'description' => 'late', 'status' => 'pending']);
        FollowUp::create(['contact_id' => $contact->contact_id, 'assigned_to' => $this->anna->user_id, 'due_date' => today()->subDay(), 'description' => 'finished', 'status' => 'done']);
        FollowUp::create(['contact_id' => $contact->contact_id, 'assigned_to' => $this->anna->user_id, 'due_date' => today(), 'description' => 'today is not overdue', 'status' => 'pending']);

        $cards = $this->dash()->viewData('cards');

        $this->assertSame(1, $cards['conversations_today']);
        $this->assertSame(1, $cards['followups_overdue']);
    }

    public function test_empty_system_renders_zeroes(): void
    {
        $this->dash()->assertOk()->assertSee('The queue is empty')->assertSee('No open tickets');
        $this->assertSame(0, $this->dash()->viewData('cards')['open']);
    }

    // ---- unassigned queue ------------------------------------------------

    public function test_queue_lists_only_open_unassigned_tickets_priority_first_then_oldest(): void
    {
        $low = Ticket::factory()->unassigned()->priority('low')->create(['title' => 'Q-low', 'created_at' => now()->subDays(9)]);
        $newCrit = Ticket::factory()->unassigned()->priority('critical')->create(['title' => 'Q-crit-new', 'created_at' => now()->subDay()]);
        $oldCrit = Ticket::factory()->unassigned()->priority('critical')->create(['title' => 'Q-crit-old', 'created_at' => now()->subDays(5)]);
        $high = Ticket::factory()->unassigned()->priority('high')->create(['title' => 'Q-high', 'created_at' => now()->subDays(2)]);
        Ticket::factory()->unassigned()->resolved()->create(['title' => 'Q-resolved']);
        Ticket::factory()->assignedTo($this->anna)->create(['title' => 'Q-owned']);

        $r = $this->dash();

        $this->assertSame(
            [$oldCrit->ticket_id, $newCrit->ticket_id, $high->ticket_id, $low->ticket_id],
            $r->viewData('queue')->pluck('ticket_id')->all()
        );
        $r->assertDontSee('Q-resolved')->assertDontSee('Q-owned')->assertSee('4 tickets waiting');
    }

    public function test_queue_is_capped_and_links_to_the_full_list(): void
    {
        Ticket::factory()->unassigned()->count(13)->create();

        $r = $this->dash();

        $this->assertCount(10, $r->viewData('queue'));
        $this->assertSame(13, $r->viewData('queueTotal'));
        $r->assertSee('See all 13 unassigned tickets');
    }

    public function test_queue_assign_dropdown_shows_each_agents_current_load_and_skips_inactive_agents(): void
    {
        $gone = User::factory()->agent()->inactive()->create(['full_name' => 'Gone Agent']);
        Ticket::factory()->unassigned()->create();
        Ticket::factory()->assignedTo($this->anna)->count(2)->create();

        $this->dash()
            ->assertSee('Anna Agent (2 open)', false)
            ->assertSee('Ben Agent (0 open)', false)
            ->assertDontSee('Gone Agent (')
            ->assertDontSee($this->admin->full_name.' (');
        $this->assertFalse(collect($this->dash()->viewData('assignable'))->contains('user_id', $gone->user_id));
    }

    public function test_assigning_from_the_queue_returns_to_the_dashboard_and_updates_everything(): void
    {
        $ticket = Ticket::factory()->unassigned()->create(['title' => 'Needs an owner']);

        $this->actingAs($this->admin)
            ->post(route('tickets.assign', $ticket), ['assigned_to' => $this->ben->user_id, 'from' => 'dashboard'])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('status');

        $this->assertSame($this->ben->user_id, $ticket->fresh()->assigned_to);
        $this->assertDatabaseHas('ticket_assignments', ['ticket_id' => $ticket->ticket_id, 'to_user' => $this->ben->user_id, 'assigned_by' => $this->admin->user_id]);
        $this->assertCount(1, $this->ben->notifications);

        $r = $this->get(route('admin.dashboard'));
        $this->assertSame(0, $r->viewData('cards')['unassigned']);
        $this->assertSame(1, (int) $this->row($r->viewData('workload'), $this->ben)->open_total);
    }

    public function test_the_return_target_cannot_be_hijacked(): void
    {
        $ticket = Ticket::factory()->unassigned()->create();

        $this->actingAs($this->admin)
            ->post(route('tickets.assign', $ticket), ['assigned_to' => $this->ben->user_id, 'from' => 'https://evil.example/'])
            ->assertRedirect(route('tickets.show', $ticket));
    }

    public function test_assign_without_an_agent_from_the_dashboard_is_rejected(): void
    {
        $ticket = Ticket::factory()->unassigned()->create();

        $this->actingAs($this->admin)->from(route('admin.dashboard'))
            ->post(route('tickets.assign', $ticket), ['assigned_to' => '', 'from' => 'dashboard'])
            ->assertRedirect(route('admin.dashboard'))->assertSessionHasErrors('assigned_to');
        $this->assertNull($ticket->fresh()->assigned_to);
    }

    // ---- workload per agent ----------------------------------------------

    public function test_workload_counts_open_tickets_by_priority_per_agent(): void
    {
        Ticket::factory()->assignedTo($this->anna)->priority('critical')->count(1)->create();
        Ticket::factory()->assignedTo($this->anna)->priority('high')->count(2)->create();
        Ticket::factory()->assignedTo($this->anna)->priority('medium')->count(3)->create();
        Ticket::factory()->assignedTo($this->anna)->priority('low')->count(4)->create();
        Ticket::factory()->assignedTo($this->anna)->resolved()->priority('critical')->count(5)->create();   // ignored: resolved
        Ticket::factory()->assignedTo($this->ben)->priority('high')->create();
        Ticket::factory()->unassigned()->priority('critical')->count(7)->create();                           // nobody's workload

        $w = $this->dash()->viewData('workload');
        $anna = $this->row($w, $this->anna);
        $ben = $this->row($w, $this->ben);

        $this->assertSame([1, 2, 3, 4, 10], [(int) $anna->critical, (int) $anna->high, (int) $anna->medium, (int) $anna->low, (int) $anna->open_total]);
        $this->assertSame([0, 1, 0, 0, 1], [(int) $ben->critical, (int) $ben->high, (int) $ben->medium, (int) $ben->low, (int) $ben->open_total]);
    }

    public function test_agents_with_zero_open_tickets_are_still_listed(): void
    {
        Ticket::factory()->assignedTo($this->anna)->count(2)->create();
        Ticket::factory()->assignedTo($this->ben)->resolved()->create();   // only resolved work

        $w = $this->dash()->viewData('workload');

        $this->assertSame(0, (int) $this->row($w, $this->ben)->open_total);
        $this->dash()->assertSee('Ben Agent')->assertSee('No open tickets');
    }

    public function test_workload_is_ordered_busiest_first_then_by_name(): void
    {
        $zed = User::factory()->agent()->create(['full_name' => 'Zed Agent']);
        $amy = User::factory()->agent()->create(['full_name' => 'Amy Agent']);
        Ticket::factory()->assignedTo($zed)->count(3)->create();
        Ticket::factory()->assignedTo($this->ben)->count(1)->create();

        $names = $this->dash()->viewData('workload')->pluck('full_name')->all();

        $this->assertSame(['Zed Agent', 'Ben Agent', 'Amy Agent', 'Anna Agent'], $names);
    }

    public function test_workload_counts_overdue_per_agent(): void
    {
        Ticket::factory()->assignedTo($this->anna)->create(['due_at' => now()->subHours(2)]);
        Ticket::factory()->assignedTo($this->anna)->create(['due_at' => now()->addDay()]);
        Ticket::factory()->assignedTo($this->anna)->create(['due_at' => null]);
        Ticket::factory()->assignedTo($this->anna)->resolved()->create(['due_at' => now()->subDay()]);

        $w = $this->dash()->viewData('workload');

        $this->assertSame(1, (int) $this->row($w, $this->anna)->overdue);
        $this->assertSame(0, (int) $this->row($w, $this->ben)->overdue);
    }

    public function test_a_deactivated_agent_with_open_tickets_stays_visible_and_flagged(): void
    {
        $gone = User::factory()->agent()->inactive()->create(['full_name' => 'Gone Agent']);
        $emptyGone = User::factory()->agent()->inactive()->create(['full_name' => 'Empty Gone']);
        Ticket::factory()->assignedTo($gone)->count(2)->create();

        $r = $this->dash();

        $this->assertNotNull($this->row($r->viewData('workload'), $gone));
        $this->assertNull($this->row($r->viewData('workload'), $emptyGone), 'inactive agents with nothing open are hidden');
        $r->assertSee('Gone Agent')->assertSee('Deactivated')->assertDontSee('Empty Gone');
    }

    public function test_admins_are_not_listed_as_agents(): void
    {
        $w = $this->dash()->viewData('workload');

        $this->assertNull($this->row($w, $this->admin));
        $this->assertCount(2, $w);
    }

    public function test_the_bar_is_scaled_to_the_busiest_agent(): void
    {
        Ticket::factory()->assignedTo($this->anna)->count(4)->create();
        Ticket::factory()->assignedTo($this->ben)->count(2)->create();

        $r = $this->dash();

        $this->assertSame(4, $r->viewData('maxOpen'));
        $r->assertSee('width:100%', false)->assertSee('width:50%', false);
    }

    public function test_each_agent_row_links_to_their_filtered_ticket_list(): void
    {
        $this->dash()->assertSee(route('tickets.index', ['assigned' => $this->anna->user_id]), false);
    }

    // ---- links and filters -----------------------------------------------

    public function test_cards_link_to_filtered_ticket_lists_that_work(): void
    {
        Ticket::factory()->unassigned()->create(['title' => 'Overdue queue one', 'due_at' => now()->subDay()]);
        Ticket::factory()->assignedTo($this->anna)->create(['title' => 'Future work', 'due_at' => now()->addDay()]);
        Ticket::factory()->assignedTo($this->anna)->resolved()->create(['title' => 'Old resolved', 'due_at' => now()->subDays(5)]);
        $this->actingAs($this->admin);

        $this->get(route('tickets.index', ['overdue' => 1]))
            ->assertSee('Overdue queue one')->assertDontSee('Future work')->assertDontSee('Old resolved');
        $this->get(route('tickets.index', ['assigned' => 'unassigned']))->assertSee('Overdue queue one')->assertDontSee('Future work');
        $this->get(route('admin.dashboard'))
            ->assertSee(route('tickets.index', ['overdue' => 1]), false)
            ->assertSee(route('tickets.index', ['priority' => 'critical']), false);
    }

    public function test_overdue_filter_for_an_agent_stays_inside_their_own_tickets(): void
    {
        Ticket::factory()->assignedTo($this->anna)->create(['title' => 'Annas late one', 'due_at' => now()->subDay()]);
        Ticket::factory()->assignedTo($this->ben)->create(['title' => 'Bens late one', 'due_at' => now()->subDay()]);

        $this->actingAs($this->anna)->get(route('tickets.index', ['overdue' => 1]))
            ->assertSee('Annas late one')->assertDontSee('Bens late one');
    }

    public function test_quick_links_restore_access_to_templates_and_modules(): void
    {
        $this->dash()
            ->assertSee(route('templates.index'), false)
            ->assertSee(route('admin.modules.index'), false)
            ->assertSee(route('admin.users.index'), false);
    }
}
