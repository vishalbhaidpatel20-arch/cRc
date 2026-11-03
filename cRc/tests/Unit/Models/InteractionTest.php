<?php

namespace Tests\Unit\Models;

use App\Models\Contact;
use App\Models\FollowUp;
use App\Models\Interaction;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InteractionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-15 12:00:00');
    }

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
            [DB::getDatabaseName(), 'interactions', $column]
        )->t;
        preg_match_all("/'([^']*)'/", $type, $m);

        return $m[1];
    }

    private function at(string $when): Interaction
    {
        return new Interaction(['interaction_at' => $when]);
    }

    private function ids(?string $term): array
    {
        return Interaction::search($term)->pluck('interactions.interaction_id')->all();
    }

    public function test_topics_match_database_enum(): void
    {
        $this->assertSame($this->enumValues('topic'), Interaction::TOPICS);
    }

    public function test_channels_match_database_enum(): void
    {
        $this->assertSame($this->enumValues('channel'), Interaction::CHANNELS);
    }

    public function test_directions_match_database_enum(): void
    {
        $this->assertSame($this->enumValues('direction'), Interaction::DIRECTIONS);
    }

    public function test_interaction_at_is_cast_to_datetime(): void
    {
        $row = Interaction::factory()->create(['interaction_at' => '2026-06-10 08:05:00'])->fresh();

        $this->assertInstanceOf(Carbon::class, $row->interaction_at);
        $this->assertSame('2026-06-10 08:05:00', $row->interaction_at->format('Y-m-d H:i:s'));
    }

    public function test_when_label_today(): void
    {
        $this->assertSame('Today, 08:05', $this->at('2026-06-15 08:05:00')->whenLabel());
    }

    public function test_when_label_yesterday(): void
    {
        $this->assertSame('Yesterday, 23:59', $this->at('2026-06-14 23:59:00')->whenLabel());
    }

    public function test_when_label_same_year(): void
    {
        $this->assertSame('18 Jan', $this->at('2026-01-18 10:00:00')->whenLabel());
    }

    public function test_when_label_other_year(): void
    {
        $this->assertSame('18 Oct 2025', $this->at('2025-10-18 10:00:00')->whenLabel());
    }

    public function test_channel_label_names(): void
    {
        $this->assertSame('Call', Interaction::channelLabel('call'));
        $this->assertSame('Email', Interaction::channelLabel('email'));
        $this->assertSame('WhatsApp', Interaction::channelLabel('whatsapp'));
        $this->assertSame('Meeting', Interaction::channelLabel('meeting'));
    }

    public function test_search_matches_summary(): void
    {
        $hit = Interaction::factory()->create(['summary' => 'Asked about the quokka invoice']);
        Interaction::factory()->create(['summary' => 'Unrelated chat']);

        $this->assertSame([$hit->interaction_id], $this->ids('quokka'));
    }

    public function test_search_matches_contact_name_case_insensitively(): void
    {
        $contact = Contact::factory()->create(['full_name' => 'Quentin Findme']);
        $hit = Interaction::factory()->create(['contact_id' => $contact->contact_id, 'summary' => 'Hello']);
        Interaction::factory()->create(['summary' => 'Hello']);

        $this->assertSame([$hit->interaction_id], $this->ids('qUENTIN'));
    }

    public function test_search_trims_term(): void
    {
        $hit = Interaction::factory()->create(['summary' => 'Asked about quokka']);
        Interaction::factory()->create(['summary' => 'Other']);

        $this->assertSame([$hit->interaction_id], $this->ids('  quokka  '));
    }

    public function test_search_blank_or_null_returns_all(): void
    {
        Interaction::factory()->count(2)->create();

        $this->assertCount(2, $this->ids(''));
        $this->assertCount(2, $this->ids('   '));
        $this->assertCount(2, $this->ids(null));
    }

    public function test_search_percent_and_underscore_are_literal(): void
    {
        $pct = Interaction::factory()->create(['summary' => 'Gave 100% discount']);
        Interaction::factory()->create(['summary' => 'Gave 100 discount']);
        $und = Interaction::factory()->create(['summary' => 'Code A_c used']);
        Interaction::factory()->create(['summary' => 'Code Abc used']);

        $this->assertSame([$pct->interaction_id], $this->ids('100%'));
        $this->assertSame([$und->interaction_id], $this->ids('A_c'));
    }

    public function test_contact_and_user_relations(): void
    {
        $contact = Contact::factory()->create();
        $user = User::factory()->agent()->create();
        $row = Interaction::factory()->create(['contact_id' => $contact->contact_id, 'user_id' => $user->user_id]);

        $this->assertTrue($row->contact->is($contact));
        $this->assertTrue($row->user->is($user));
    }

    public function test_ticket_relation_is_null_without_ticket(): void
    {
        $this->assertNull(Interaction::factory()->create()->ticket);
    }

    public function test_ticket_relation_returns_ticket_for_admin(): void
    {
        $agent = User::factory()->agent()->create();
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->assignedTo($agent)->create();
        $row = Interaction::factory()->create(['ticket_id' => $ticket->ticket_id]);

        $this->actingAs($admin);

        $this->assertTrue($row->fresh()->ticket->is($ticket));
    }

    public function test_ticket_relation_is_hidden_by_global_scope_for_another_agent(): void
    {
        $owner = User::factory()->agent()->create();
        $other = User::factory()->agent()->create();
        $ticket = Ticket::factory()->assignedTo($owner)->create();
        $row = Interaction::factory()->create(['ticket_id' => $ticket->ticket_id]);

        $this->actingAs($other);

        $this->assertNull($row->fresh()->ticket);
    }

    public function test_ticket_relation_is_visible_to_the_assigned_agent(): void
    {
        $owner = User::factory()->agent()->create();
        $ticket = Ticket::factory()->assignedTo($owner)->create();
        $row = Interaction::factory()->create(['ticket_id' => $ticket->ticket_id]);

        $this->actingAs($owner);

        $this->assertTrue($row->fresh()->ticket->is($ticket));
    }

    public function test_follow_up_relation(): void
    {
        $row = Interaction::factory()->create();
        $followUp = FollowUp::create([
            'contact_id' => $row->contact_id,
            'interaction_id' => $row->interaction_id,
            'assigned_to' => $row->user_id,
            'due_date' => '2026-06-20',
            'description' => 'Call back',
        ]);

        $this->assertTrue($row->followUp->is($followUp));
    }

    public function test_follow_up_relation_is_null_when_none(): void
    {
        $this->assertNull(Interaction::factory()->create()->followUp);
    }
}
