<?php

namespace Tests\Feature\E2E;

use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Milestone 5 journey: the admin opens the dashboard, sees the unassigned queue and who is
 * overloaded, assigns from the queue to the lightest agent, and the numbers move. The agent
 * only ever sees their own work, and never the team dashboard.
 */
class DashboardJourneyTest extends TestCase
{
    use JourneyHelpers;
    use RefreshDatabase;

    public function test_admin_balances_the_team_from_the_dashboard(): void
    {
        $admin = $this->makeAdmin(['full_name' => 'Boss Admin']);
        $busy = $this->makeAgent(['full_name' => 'Busy Bella', 'email' => 'bella@example.com']);
        $free = $this->makeAgent(['full_name' => 'Free Fred', 'email' => 'fred@example.com']);

        Ticket::factory()->assignedTo($busy)->priority('critical')->create(['title' => 'Bella critical one']);
        Ticket::factory()->assignedTo($busy)->priority('high')->count(3)->create();
        Ticket::factory()->unassigned()->priority('critical')->create(['title' => 'Queue: payroll is down', 'created_at' => now()->subDays(2)]);
        Ticket::factory()->unassigned()->priority('low')->create(['title' => 'Queue: change my logo']);

        // 1. The admin sees the whole picture on one page.
        $this->loginAs($admin->email)->assertSee('Admin dashboard')->assertSee('Unassigned queue')->assertSee('Workload per agent');
        $page = $this->browse(route('admin.dashboard'))
            ->assertSee('Queue: payroll is down')->assertSee('Queue: change my logo')
            ->assertSee('Busy Bella')->assertSee('Free Fred')->assertSee('No open tickets')
            ->assertSee('2 tickets waiting');
        $html = $page->getContent();
        $this->assertLessThan(strpos($html, 'Queue: change my logo'), strpos($html, 'Queue: payroll is down'), 'critical comes first in the queue');
        $this->assertLessThan(strpos($html, 'Free Fred'), strpos($html, 'Busy Bella'), 'the busiest agent is listed first');
        $page->assertSee('Busy Bella (4 open)', false)->assertSee('Free Fred (0 open)', false);

        // 2. They assign the critical ticket to the agent with the lightest load, right from the queue.
        $critical = Ticket::where('title', 'Queue: payroll is down')->firstOrFail();
        $this->submit('POST', route('tickets.assign', $critical), [
            'assigned_to' => $free->user_id, 'from' => 'dashboard',
        ], route('admin.dashboard'))
            ->assertOk()->assertSee('Admin dashboard')->assertSee($critical->ticket_no.' assigned to Free Fred')
            ->assertSee('1 ticket waiting')->assertDontSee('Queue: payroll is down')
            ->assertSee('Free Fred (1 open)', false);

        // 3. The history was written and the numbers moved.
        $this->assertSame(1, $critical->assignments()->count());
        $workload = $this->freshRequest()->get(route('admin.dashboard'))->viewData('workload');
        $this->assertSame(1, (int) $workload->firstWhere('user_id', $free->user_id)->open_total);
        $this->assertSame(4, (int) $workload->firstWhere('user_id', $busy->user_id)->open_total);
        $this->logout();

        // 4. Fred sees the new ticket and the alert; he never sees the team dashboard or Bella's work.
        $this->loginAs($free->email)->assertSee('Agent dashboard')->assertSee('Queue: payroll is down')->assertSee('assigned by Boss Admin');
        $this->browse(route('tickets.index'))->assertSee('Queue: payroll is down')->assertDontSee('Bella critical one');
        $this->freshRequest()->get(route('admin.dashboard'))->assertForbidden();
        $this->browse(route('agent.dashboard'))->assertDontSee('Workload per agent')->assertDontSee('Queue: change my logo');
    }

    public function test_dashboard_cards_lead_to_the_matching_ticket_lists(): void
    {
        $admin = $this->makeAdmin();
        $agent = $this->makeAgent();
        Ticket::factory()->unassigned()->create(['title' => 'Waiting in queue']);
        Ticket::factory()->assignedTo($agent)->create(['title' => 'Late and owned', 'due_at' => now()->subDay()]);
        Ticket::factory()->assignedTo($agent)->create(['title' => 'On time and owned', 'due_at' => now()->addDays(3)]);

        $this->loginAs($admin->email);
        $this->browse(route('tickets.index', ['assigned' => 'unassigned']))->assertSee('Waiting in queue')->assertDontSee('Late and owned');
        $this->browse(route('tickets.index', ['overdue' => 1]))->assertSee('Late and owned')->assertDontSee('On time and owned')->assertDontSee('Waiting in queue');
        $this->browse(route('tickets.index', ['assigned' => $agent->user_id]))->assertSee('Late and owned')->assertSee('On time and owned')->assertDontSee('Waiting in queue');
    }
}
