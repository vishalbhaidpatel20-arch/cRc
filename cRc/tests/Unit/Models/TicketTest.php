<?php

namespace Tests\Unit\Models;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return list<string> */
    private function enumValues(string $column): array
    {
        $type = DB::selectOne(
            'SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [DB::getDatabaseName(), 'tickets', $column]
        )->t;
        preg_match_all("/'([^']*)'/", $type, $m);

        return $m[1];
    }

    private function loginAs(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs($user);
    }

    private function ids(?string $term): array
    {
        return Ticket::search($term)->pluck('ticket_id')->all();
    }

    public function test_topics_match_database_enum(): void
    {
        $this->assertSame($this->enumValues('topic'), Ticket::TOPICS);
    }

    public function test_priorities_match_database_enum(): void
    {
        $this->assertSame($this->enumValues('priority'), Ticket::PRIORITIES);
    }

    public function test_statuses_match_database_enum(): void
    {
        $this->assertSame($this->enumValues('status'), Ticket::STATUSES);
    }

    public function test_primary_key_is_ticket_id(): void
    {
        $this->assertSame('ticket_id', (new Ticket)->getKeyName());
    }

    public function test_due_at_and_timestamps_are_cast_to_datetime(): void
    {
        $ticket = Ticket::factory()->create(['due_at' => '2026-06-20 09:30:00'])->fresh();

        $this->assertInstanceOf(Carbon::class, $ticket->due_at);
        $this->assertSame('2026-06-20 09:30:00', $ticket->due_at->format('Y-m-d H:i:s'));
        $this->assertInstanceOf(Carbon::class, $ticket->created_at);
        $this->assertInstanceOf(Carbon::class, $ticket->updated_at);
    }

    public function test_null_due_at_stays_null(): void
    {
        $this->assertNull(Ticket::factory()->create()->fresh()->due_at);
    }

    public function test_company_and_contact_relations(): void
    {
        $ticket = Ticket::factory()->create();

        $this->assertInstanceOf(Company::class, $ticket->company);
        $this->assertInstanceOf(Contact::class, $ticket->contact);
        $this->assertSame($ticket->contact->company_id, $ticket->company->company_id);
    }

    public function test_assignee_relation_returns_the_assigned_user(): void
    {
        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->assignedTo($agent)->create();

        $this->assertTrue($ticket->assignee->is($agent));
    }

    public function test_assignee_is_null_when_unassigned(): void
    {
        $this->assertNull(Ticket::factory()->unassigned()->create()->assignee);
    }

    public function test_assignments_relation_returns_history_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->create();
        TicketAssignment::create([
            'ticket_id' => $ticket->ticket_id, 'to_user' => $agent->user_id,
            'assigned_by' => $admin->user_id, 'assigned_at' => '2026-06-15 10:00:00',
        ]);

        $this->assertCount(1, $ticket->assignments);
        $this->assertSame($agent->user_id, $ticket->assignments->first()->to_user);
    }

    public function test_is_overdue_false_without_due_date(): void
    {
        $this->assertFalse(Ticket::factory()->make(['due_at' => null])->isOverdue());
    }

    public function test_is_overdue_false_for_future_due_date(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');

        $this->assertFalse(Ticket::factory()->make(['due_at' => '2026-06-15 12:00:01'])->isOverdue());
    }

    public function test_is_overdue_true_for_past_due_date(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');

        $this->assertTrue(Ticket::factory()->make(['due_at' => '2026-06-15 11:59:59'])->isOverdue());
    }

    public function test_is_overdue_false_for_resolved_ticket_with_past_due_date(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');

        $this->assertFalse(Ticket::factory()->resolved()->make(['due_at' => '2026-06-01 12:00:00'])->isOverdue());
    }

    public function test_scope_open_excludes_only_resolved(): void
    {
        $statuses = [];
        foreach (Ticket::STATUSES as $status) {
            $statuses[$status] = Ticket::factory()->create(['status' => $status])->ticket_id;
        }

        $open = Ticket::open()->pluck('ticket_id')->all();

        $this->assertEqualsCanonicalizing(
            [$statuses['open'], $statuses['in_progress'], $statuses['waiting']],
            $open
        );
    }

    public function test_scope_by_priority_orders_critical_high_medium_low(): void
    {
        $low = Ticket::factory()->priority('low')->create();
        $critical = Ticket::factory()->priority('critical')->create();
        $medium = Ticket::factory()->priority('medium')->create();
        $high = Ticket::factory()->priority('high')->create();

        $this->assertSame(
            [$critical->ticket_id, $high->ticket_id, $medium->ticket_id, $low->ticket_id],
            Ticket::byPriority()->pluck('ticket_id')->all()
        );
    }

    public function test_search_matches_ticket_no(): void
    {
        $hit = Ticket::factory()->create(['ticket_no' => 'TKT-ZZ9001']);
        Ticket::factory()->create(['ticket_no' => 'TKT-AA0001']);

        $this->assertSame([$hit->ticket_id], $this->ids('ZZ9001'));
    }

    public function test_search_matches_title(): void
    {
        $hit = Ticket::factory()->create(['title' => 'Printer on fire']);
        Ticket::factory()->create(['title' => 'Something else']);

        $this->assertSame([$hit->ticket_id], $this->ids('Printer'));
    }

    public function test_search_matches_description(): void
    {
        $hit = Ticket::factory()->create(['description' => 'Customer reports a quokka in the server room']);
        Ticket::factory()->create(['description' => 'Nothing to see']);

        $this->assertSame([$hit->ticket_id], $this->ids('quokka'));
    }

    public function test_search_is_case_insensitive(): void
    {
        $hit = Ticket::factory()->create(['title' => 'Printer on fire']);

        $this->assertSame([$hit->ticket_id], $this->ids('pRINTER'));
    }

    public function test_search_trims_the_term(): void
    {
        $hit = Ticket::factory()->create(['title' => 'Printer on fire']);
        Ticket::factory()->create(['title' => 'Other']);

        $this->assertSame([$hit->ticket_id], $this->ids('   Printer   '));
    }

    public function test_search_blank_or_null_returns_all(): void
    {
        Ticket::factory()->count(2)->create();

        $this->assertCount(2, $this->ids(''));
        $this->assertCount(2, $this->ids('   '));
        $this->assertCount(2, $this->ids(null));
    }

    public function test_search_percent_is_literal(): void
    {
        $hit = Ticket::factory()->create(['title' => '100% broken']);
        Ticket::factory()->create(['title' => '100 broken']);

        $this->assertSame([$hit->ticket_id], $this->ids('100%'));
        $this->assertSame([$hit->ticket_id], $this->ids('%'));
    }

    public function test_search_underscore_is_literal(): void
    {
        $hit = Ticket::factory()->create(['title' => 'A_c failure']);
        Ticket::factory()->create(['title' => 'Abc failure']);

        $this->assertSame([$hit->ticket_id], $this->ids('A_c'));
    }

    // ---- agent_visibility global scope ----

    public function test_nobody_logged_in_sees_all_tickets(): void
    {
        $agent = User::factory()->agent()->create();
        Ticket::factory()->assignedTo($agent)->create();
        Ticket::factory()->unassigned()->create();

        $this->assertSame(2, Ticket::count());
    }

    public function test_admin_sees_all_tickets(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->agent()->create();
        Ticket::factory()->assignedTo($agent)->create();
        Ticket::factory()->unassigned()->create();

        $this->loginAs($admin);

        $this->assertSame(2, Ticket::count());
    }

    public function test_agent_sees_only_tickets_assigned_to_them(): void
    {
        $me = User::factory()->agent()->create();
        $other = User::factory()->agent()->create();
        $mine = Ticket::factory()->assignedTo($me)->create();
        Ticket::factory()->assignedTo($other)->create();
        Ticket::factory()->unassigned()->create();

        $this->loginAs($me);

        $this->assertSame([$mine->ticket_id], Ticket::pluck('ticket_id')->all());
    }

    public function test_agent_scope_applies_to_find_count_pluck_and_exists(): void
    {
        $me = User::factory()->agent()->create();
        $other = User::factory()->agent()->create();
        $mine = Ticket::factory()->assignedTo($me)->create();
        $notMine = Ticket::factory()->assignedTo($other)->create();

        $this->loginAs($me);

        $this->assertNotNull(Ticket::find($mine->ticket_id));
        $this->assertNull(Ticket::find($notMine->ticket_id));
        $this->assertSame(1, Ticket::count());
        $this->assertSame([$mine->ticket_id], Ticket::pluck('ticket_id')->all());
        $this->assertTrue(Ticket::whereKey($mine->ticket_id)->exists());
        $this->assertFalse(Ticket::whereKey($notMine->ticket_id)->exists());
    }

    public function test_without_global_scopes_bypasses_agent_visibility(): void
    {
        $me = User::factory()->agent()->create();
        $other = User::factory()->agent()->create();
        Ticket::factory()->assignedTo($me)->create();
        Ticket::factory()->assignedTo($other)->create();

        $this->loginAs($me);

        $this->assertSame(2, Ticket::withoutGlobalScopes()->count());
        $this->assertSame(2, Ticket::withoutGlobalScope('agent_visibility')->count());
    }

    public function test_two_agents_get_different_results(): void
    {
        $a = User::factory()->agent()->create();
        $b = User::factory()->agent()->create();
        $ta = Ticket::factory()->assignedTo($a)->create();
        $tb = Ticket::factory()->assignedTo($b)->create();

        $this->loginAs($a);
        $this->assertSame([$ta->ticket_id], Ticket::pluck('ticket_id')->all());

        $this->loginAs($b);
        $this->assertSame([$tb->ticket_id], Ticket::pluck('ticket_id')->all());
    }

    public function test_agent_with_no_tickets_sees_none(): void
    {
        $agent = User::factory()->agent()->create();
        Ticket::factory()->unassigned()->create();

        $this->loginAs($agent);

        $this->assertSame(0, Ticket::count());
    }

    public function test_scope_combines_with_search_and_open(): void
    {
        $me = User::factory()->agent()->create();
        $other = User::factory()->agent()->create();
        $hit = Ticket::factory()->assignedTo($me)->create(['title' => 'Needle one']);
        Ticket::factory()->assignedTo($me)->resolved()->create(['title' => 'Needle two']);
        Ticket::factory()->assignedTo($other)->create(['title' => 'Needle three']);

        $this->loginAs($me);

        $this->assertSame([$hit->ticket_id], Ticket::open()->search('Needle')->pluck('ticket_id')->all());
    }
}
