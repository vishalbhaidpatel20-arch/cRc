<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The left sidebar (partials/sidebar, always visible, not in the header): links to the ticket list by status
 * (all / open / in progress / waiting / resolved) for every signed-in user.
 */
class SideMenuTest extends TestCase
{
    use RefreshDatabase;

    private const LINKS = [
        'all' => 'All tickets',
        'open' => 'Open tickets',
        'in_progress' => 'In progress tickets',
        'waiting' => 'Waiting tickets',
        'resolved' => 'Resolved tickets',
    ];

    public function test_every_signed_in_page_has_the_menu_button_and_the_status_links(): void
    {
        foreach ([User::factory()->admin()->create(), User::factory()->agent()->create()] as $user) {
            $this->app['auth']->forgetGuards();
            $page = $this->actingAs($user)->get(route($user->homeRoute()))->assertOk()
                ->assertSee('<aside id="side-menu"', false)
                ->assertSee('js/sidebar.js" defer></script>', false);

            foreach (self::LINKS as $status => $label) {
                $page->assertSee('href="'.route('tickets.index', ['status' => $status]).'"', false)->assertSee($label);
            }
        }
    }

    public function test_the_shown_list_is_marked_active_and_there_are_no_counts(): void
    {
        $alice = User::factory()->agent()->create();
        Ticket::factory()->count(2)->assignedTo($alice)->create(['status' => 'waiting']);

        $this->actingAs($alice)->get(route('tickets.index', ['status' => 'waiting']))->assertOk()
            ->assertSee('class="side-link st-waiting active"', false)
            ->assertSee('aria-label="Waiting tickets"', false)
            ->assertDontSee('side-count', false)
            ->assertSee('data-sync', false);

        $this->app['auth']->forgetGuards();
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.users.index'))
            ->assertDontSee('side-link st-waiting active', false);   // not on the ticket list: nothing active
    }

    public function test_guests_do_not_get_the_menu(): void
    {
        $this->get(route('login'))->assertOk()->assertDontSee('<aside id="side-menu"', false)->assertDontSee('js/sidebar.js" defer></script>', false);
    }

    public function test_each_link_lists_only_tickets_with_that_status_and_agents_only_their_own(): void
    {
        $alice = User::factory()->agent()->create();
        $bob = User::factory()->agent()->create();
        foreach (['open', 'in_progress', 'waiting', 'resolved'] as $status) {
            Ticket::factory()->assignedTo($alice)->create(['status' => $status, 'title' => "Alice {$status} ticket"]);
            Ticket::factory()->assignedTo($bob)->create(['status' => $status, 'title' => "Bob {$status} ticket"]);
        }

        foreach (['open', 'in_progress', 'waiting', 'resolved'] as $status) {
            $page = $this->actingAs($alice)->get(route('tickets.index', ['status' => $status]))->assertOk()
                ->assertSee("Alice {$status} ticket")
                ->assertDontSee("Bob {$status} ticket");

            foreach (array_diff(['open', 'in_progress', 'waiting', 'resolved'], [$status]) as $other) {
                $page->assertDontSee("Alice {$other} ticket");
            }
        }

        $this->actingAs($alice)->get(route('tickets.index', ['status' => 'all']))
            ->assertSee('Alice open ticket')->assertSee('Alice resolved ticket')->assertDontSee('Bob open ticket');
    }
}
