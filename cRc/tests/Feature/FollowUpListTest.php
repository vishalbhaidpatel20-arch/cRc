<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\FollowUp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Follow-ups page (reached from the dashboard cards and the menu): who sees what, filters, search, mark done. */
class FollowUpListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['full_name' => 'Ada Admin']);
        $this->alice = User::factory()->agent()->create(['full_name' => 'Alice Agent']);
        $this->bob = User::factory()->agent()->create(['full_name' => 'Bob Agent']);
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user);
    }

    private function make(User $agent, string $text, int $daysFromToday, string $status = 'pending', string $contactName = 'Client Person'): FollowUp
    {
        return FollowUp::create([
            'contact_id' => Contact::factory()->create(['full_name' => $contactName])->contact_id,
            'assigned_to' => $agent->user_id,
            'due_date' => today()->addDays($daysFromToday),
            'description' => $text,
            'status' => $status,
            'completed_at' => $status === 'done' ? now() : null,
        ]);
    }

    private function seedSet(): void
    {
        $this->make($this->alice, 'Alice overdue call', -3);
        $this->make($this->alice, 'Alice today call', 0);
        $this->make($this->alice, 'Alice future call', 5);
        $this->make($this->alice, 'Alice finished call', -1, 'done');
        $this->make($this->bob, 'Bob overdue call', -2);
    }

    public function test_an_agent_only_sees_their_own_follow_ups(): void
    {
        $this->seedSet();

        $this->as($this->alice)->get(route('follow-ups.index'))->assertOk()
            ->assertSee('Alice overdue call')->assertSee('Alice today call')->assertSee('Alice future call')
            ->assertDontSee('Bob overdue call')->assertDontSee('Alice finished call');   // done ones are hidden by the default filter

        // An agent cannot look at someone else's by passing ?agent=.
        $this->get(route('follow-ups.index', ['agent' => $this->bob->user_id, 'filter' => 'all']))
            ->assertSee('Alice overdue call')->assertDontSee('Bob overdue call');
    }

    public function test_an_admin_sees_everyones_and_can_narrow_to_one_agent(): void
    {
        $this->seedSet();

        $this->as($this->admin)->get(route('follow-ups.index'))->assertOk()
            ->assertSee('Alice overdue call')->assertSee('Bob overdue call')->assertSee('Alice Agent')->assertSee('Bob Agent');

        $this->get(route('follow-ups.index', ['agent' => $this->bob->user_id]))
            ->assertSee('Bob overdue call')->assertDontSee('Alice overdue call');
    }

    public function test_the_filters_pick_the_right_rows(): void
    {
        $this->seedSet();
        $this->as($this->alice);

        $this->get(route('follow-ups.index', ['filter' => 'overdue']))
            ->assertSee('Alice overdue call')->assertDontSee('Alice today call')->assertDontSee('Alice future call')->assertDontSee('Alice finished call');
        $this->get(route('follow-ups.index', ['filter' => 'due']))
            ->assertSee('Alice overdue call')->assertSee('Alice today call')->assertDontSee('Alice future call');
        $this->get(route('follow-ups.index', ['filter' => 'today']))
            ->assertSee('Alice today call')->assertDontSee('Alice overdue call')->assertDontSee('Alice future call');
        $this->get(route('follow-ups.index', ['filter' => 'upcoming']))
            ->assertSee('Alice future call')->assertDontSee('Alice today call')->assertDontSee('Alice overdue call');
        $this->get(route('follow-ups.index', ['filter' => 'done']))
            ->assertSee('Alice finished call')->assertDontSee('Alice overdue call');
        $this->get(route('follow-ups.index', ['filter' => 'all']))
            ->assertSee('Alice finished call')->assertSee('Alice overdue call')->assertSee('Alice future call');

        // An unknown filter falls back to "pending".
        $this->get(route('follow-ups.index', ['filter' => 'nonsense']))
            ->assertSee('Alice overdue call')->assertDontSee('Alice finished call');
    }

    public function test_the_dashboard_overdue_card_matches_the_overdue_list(): void
    {
        $this->seedSet();
        $this->make($this->bob, 'Bob second overdue', -9);

        $dash = $this->as($this->admin)->get(route('admin.dashboard'))->assertOk();
        $this->assertSame(3, $dash->viewData('cards')['followups_overdue']);
        $dash->assertSee(route('follow-ups.index', ['filter' => 'overdue']), false);

        $list = $this->get(route('follow-ups.index', ['filter' => 'overdue']))->assertOk();
        $this->assertSame(3, $list->viewData('followUps')->total(), 'the card number and the list agree');
        $list->assertSee('Alice overdue call')->assertSee('Bob overdue call')->assertSee('Bob second overdue');
    }

    public function test_the_agent_dashboard_card_links_to_their_due_list(): void
    {
        $this->seedSet();

        $this->as($this->alice)->get(route('agent.dashboard'))->assertOk()
            ->assertSee(route('follow-ups.index', ['filter' => 'due']), false);
    }

    public function test_search_finds_by_contact_company_or_what_was_promised(): void
    {
        $this->make($this->alice, 'Send the quote', 1, 'pending', 'Priya Contact');
        $this->make($this->alice, 'Confirm the demo', 1, 'pending', 'Rahul Contact');

        $this->as($this->alice);
        $this->get(route('follow-ups.index', ['q' => 'quote']))->assertSee('Send the quote')->assertDontSee('Confirm the demo');
        $this->get(route('follow-ups.index', ['q' => 'Rahul']))->assertSee('Confirm the demo')->assertDontSee('Send the quote');
        $this->get(route('follow-ups.index', ['q' => 'nothing-matches-xyz']))->assertSee('No follow-ups found.');
    }

    public function test_marking_done_from_the_list_works_for_the_owner_and_the_list_updates(): void
    {
        $f = $this->make($this->alice, 'Call Dora back', 0);

        $page = $this->as($this->alice)->get(route('follow-ups.index'))->assertOk()->getContent();
        $this->assertStringContainsString('action="'.route('follow-ups.done', $f).'"', $page);
        $this->assertStringContainsString('Mark done', $page);

        $this->from(route('follow-ups.index'))->patch(route('follow-ups.done', $f))->assertRedirect(route('follow-ups.index'));
        $this->assertSame('done', $f->fresh()->status);

        $this->get(route('follow-ups.index'))->assertDontSee('Call Dora back');
        $this->get(route('follow-ups.index', ['filter' => 'done']))->assertSee('Call Dora back');

        // Someone else's follow-up is still protected.
        $other = $this->make($this->bob, 'Bobs one', 0);
        $this->as($this->alice)->patch(route('follow-ups.done', $other))->assertForbidden();
    }

    public function test_the_menu_has_a_follow_ups_link_for_everyone_and_guests_are_sent_to_login(): void
    {
        $this->as($this->admin)->get(route('follow-ups.index'))->assertOk()->assertSee('href="'.route('follow-ups.index').'"', false);
        $this->as($this->alice)->get(route('agent.dashboard'))->assertSee('href="'.route('follow-ups.index').'"', false);

        $this->app['auth']->forgetGuards();
        auth()->logout();
        $this->get(route('follow-ups.index'))->assertRedirect(route('login'));
    }

    public function test_an_empty_list_shows_the_empty_message(): void
    {
        $this->as($this->alice)->get(route('follow-ups.index'))->assertOk()->assertSee('No follow-ups found.');
    }
}
