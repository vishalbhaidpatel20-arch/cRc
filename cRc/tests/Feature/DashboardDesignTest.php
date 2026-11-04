<?php

namespace Tests\Feature;

use App\Models\Interaction;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The redesigned dashboards: greeting header, stat cards, breakdown panels, full width, agent name in the title. */
class DashboardDesignTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $divya;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['full_name' => 'Anita Shah']);
        $this->divya = User::factory()->agent()->create(['full_name' => 'Divya Menon']);
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user);
    }

    public function test_the_admin_dashboard_is_titled_with_the_admins_first_name(): void
    {
        $this->as(User::factory()->admin()->create(['full_name' => 'Vishal Patel']))->get(route('admin.dashboard'))->assertOk()
            ->assertSee("Vishal's dashboard", false)
            ->assertSee('Welcome, Vishal Patel.')
            ->assertSee('<title>Admin dashboard - cRc</title>', false);   // the browser tab keeps the generic title
    }

    public function test_the_agent_dashboard_is_titled_with_the_agents_first_name(): void
    {
        $this->as($this->divya)->get(route('agent.dashboard'))->assertOk()
            ->assertSee("Divya's dashboard", false)
            ->assertSee('Welcome, Divya Menon.')
            ->assertSee('<title>Agent dashboard - cRc</title>', false);   // the browser tab keeps the generic title

        $this->as(User::factory()->agent()->create(['full_name' => 'Zoe']))->get(route('agent.dashboard'))
            ->assertSee("Zoe's dashboard", false);
    }

    public function test_both_dashboards_use_the_full_page_width(): void
    {
        $rule = 'main:has(.dash-hero),main:has(.contact-grid){max-width:none;padding:0 1.5rem}';

        $this->as($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('class="dash-hero"', false)->assertSee($rule, false);
        $this->as($this->divya)->get(route('agent.dashboard'))->assertOk()
            ->assertSee('class="dash-hero"', false)->assertSee($rule, false);
    }

    public function test_the_admin_dashboard_has_icon_stat_cards_and_breakdown_panels(): void
    {
        Ticket::factory()->unassigned()->priority('critical')->create(['status' => 'open']);
        Ticket::factory()->assignedTo($this->divya)->priority('critical')->create(['status' => 'in_progress']);
        Ticket::factory()->assignedTo($this->divya)->priority('low')->create(['status' => 'waiting']);
        Ticket::factory()->assignedTo($this->divya)->priority('low')->resolved()->create();   // resolved: not counted

        $r = $this->as($this->admin)->get(route('admin.dashboard'))->assertOk();
        $r->assertSee('class="dstat tone-', false)->assertSee('dstat-ico', false)
            ->assertSee('Open tickets by priority')->assertSee('Open tickets by status')
            ->assertSee('Recent conversations');

        $this->assertSame(2, (int) $r->viewData('byPriority')['critical']);
        $this->assertSame(1, (int) $r->viewData('byPriority')['low']);
        $this->assertEquals(['in_progress' => 1, 'open' => 1, 'waiting' => 1], $r->viewData('byStatus')->map(fn ($n) => (int) $n)->sortKeys()->all());

        // The bars are scaled to the biggest value (critical = 2 -> 100%, low = 1 -> 50%).
        $r->assertSee('width:100%;background:#d94b4b', false)->assertSee('width:50%;background:#b5bcc6', false);

        // The breakdown rows link to the filtered ticket list.
        $r->assertSee(route('tickets.index', ['priority' => 'critical']), false)
            ->assertSee(route('tickets.index', ['status' => 'in_progress']), false);
    }

    public function test_the_admin_dashboard_lists_the_latest_five_conversations(): void
    {
        foreach (range(1, 7) as $i) {
            Interaction::factory()->create(['summary' => "Conversation number {$i}", 'interaction_at' => now()->subHours(10 - $i)]);
        }

        $r = $this->as($this->admin)->get(route('admin.dashboard'))->assertOk();

        $this->assertCount(5, $r->viewData('recent'));
        $r->assertSee('Conversation number 7')->assertSee('Conversation number 3')
            ->assertDontSee('Conversation number 2')->assertDontSee('Conversation number 1');
    }

    public function test_empty_dashboards_show_friendly_empty_states(): void
    {
        $this->as($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('No open tickets right now.')->assertSee('No conversations logged yet.');

        $this->as($this->divya)->get(route('agent.dashboard'))->assertOk()
            ->assertSee('No open tickets. Nice work.')->assertSee('No follow-ups due.')->assertSee('No new assignments.');
    }

    public function test_the_agent_dashboard_counts_overdue_and_links_cards(): void
    {
        Ticket::factory()->assignedTo($this->divya)->create(['due_at' => now()->subDay()]);
        Ticket::factory()->assignedTo($this->divya)->create(['due_at' => now()->addDays(3)]);
        Ticket::factory()->assignedTo($this->divya)->resolved()->create(['due_at' => now()->subDays(5)]);   // resolved: not overdue

        $r = $this->as($this->divya)->get(route('agent.dashboard'))->assertOk();

        $this->assertSame(2, $r->viewData('openCount'));
        $this->assertSame(1, $r->viewData('overdueCount'));
        $r->assertSee(route('tickets.index', ['overdue' => 1]), false)->assertSee(route('notifications.index'), false)
            ->assertSee('My tickets by priority');
    }

    public function test_the_contact_page_uses_the_full_page_width(): void
    {
        $contact = \App\Models\Contact::factory()->create();

        $this->as($this->admin)->get(route('contacts.show', $contact))->assertOk()
            ->assertSee('class="contact-grid"', false)
            ->assertSee('main:has(.contact-grid){max-width:none;padding:0 1.5rem}', false);
    }
}
