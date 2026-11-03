<?php

namespace Tests\Unit\Models;

use App\Models\Contact;
use App\Models\FollowUp;
use App\Models\Interaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FollowUpTest extends TestCase
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

    /** Unsaved follow-up, enough for the pure helpers. */
    private function make(string $due, string $status = 'pending'): FollowUp
    {
        return new FollowUp(['due_date' => $due, 'status' => $status]);
    }

    private function create(array $attrs = []): FollowUp
    {
        return FollowUp::create(array_merge([
            'contact_id' => Contact::factory()->create()->contact_id,
            'assigned_to' => User::factory()->agent()->create()->user_id,
            'due_date' => '2026-06-16',
            'description' => 'Call back',
        ], $attrs));
    }

    public function test_table_and_key_configuration(): void
    {
        $model = new FollowUp;

        $this->assertSame('follow_ups', $model->getTable());
        $this->assertSame('follow_up_id', $model->getKeyName());
        $this->assertFalse($model->usesTimestamps());
    }

    public function test_casts_due_date_to_date_and_completed_at_to_datetime(): void
    {
        $row = $this->create(['status' => 'done', 'completed_at' => '2026-06-15 09:15:00'])->fresh();

        $this->assertInstanceOf(Carbon::class, $row->due_date);
        $this->assertSame('2026-06-16', $row->due_date->format('Y-m-d'));
        $this->assertSame('2026-06-15 09:15:00', $row->completed_at->format('Y-m-d H:i:s'));
    }

    public function test_status_defaults_to_pending_and_completed_at_to_null(): void
    {
        $row = $this->create()->fresh();

        $this->assertSame('pending', $row->status);
        $this->assertNull($row->completed_at);
    }

    public function test_contact_and_assignee_relations(): void
    {
        $contact = Contact::factory()->create();
        $agent = User::factory()->agent()->create();
        $row = $this->create(['contact_id' => $contact->contact_id, 'assigned_to' => $agent->user_id]);

        $this->assertTrue($row->contact->is($contact));
        $this->assertTrue($row->assignee->is($agent));
    }

    public function test_interaction_relation(): void
    {
        $interaction = Interaction::factory()->create();
        $row = $this->create(['interaction_id' => $interaction->interaction_id]);

        $this->assertTrue($row->interaction->is($interaction));
    }

    public function test_interaction_relation_is_null_without_interaction(): void
    {
        $this->assertNull($this->create()->interaction);
    }

    public function test_pending_follow_up_due_yesterday_is_overdue(): void
    {
        $this->assertTrue($this->make('2026-06-14')->isOverdue());
    }

    public function test_pending_follow_up_due_today_is_not_overdue(): void
    {
        $this->assertFalse($this->make('2026-06-15')->isOverdue());
    }

    public function test_pending_follow_up_due_tomorrow_is_not_overdue(): void
    {
        $this->assertFalse($this->make('2026-06-16')->isOverdue());
    }

    public function test_done_follow_up_in_the_past_is_not_overdue(): void
    {
        $this->assertFalse($this->make('2026-06-14', 'done')->isOverdue());
    }

    public function test_cancelled_follow_up_in_the_past_is_not_overdue(): void
    {
        $this->assertFalse($this->make('2026-06-14', 'cancelled')->isOverdue());
    }

    public function test_due_label_today(): void
    {
        $this->assertSame('today', $this->make('2026-06-15')->dueLabel());
    }

    public function test_due_label_tomorrow(): void
    {
        $this->assertSame('tomorrow', $this->make('2026-06-16')->dueLabel());
    }

    public function test_due_label_yesterday(): void
    {
        $this->assertSame('yesterday', $this->make('2026-06-14')->dueLabel());
    }

    public function test_due_label_same_year_omits_year(): void
    {
        $this->assertSame('on 5 Dec', $this->make('2026-12-05')->dueLabel());
    }

    public function test_due_label_other_year_includes_year(): void
    {
        $this->assertSame('on 5 Dec 2030', $this->make('2030-12-05')->dueLabel());
    }

    public function test_due_label_past_other_year_includes_year(): void
    {
        $this->assertSame('on 31 Dec 2025', $this->make('2025-12-31')->dueLabel());
    }
}
