<?php

namespace Tests\Unit\Models;

use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TicketAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function assignment(array $attrs = []): TicketAssignment
    {
        return TicketAssignment::create(array_merge([
            'ticket_id' => Ticket::factory()->create()->ticket_id,
            'from_user' => null,
            'to_user' => User::factory()->agent()->create()->user_id,
            'assigned_by' => User::factory()->admin()->create()->user_id,
            'reason' => 'Initial',
            'assigned_at' => '2026-06-15 10:00:00',
        ], $attrs));
    }

    public function test_table_key_and_timestamps_configuration(): void
    {
        $model = new TicketAssignment;

        $this->assertSame('ticket_assignments', $model->getTable());
        $this->assertSame('assignment_id', $model->getKeyName());
        $this->assertFalse($model->usesTimestamps());
    }

    public function test_fillable_attributes(): void
    {
        $this->assertSame(
            ['ticket_id', 'from_user', 'to_user', 'assigned_by', 'reason', 'assigned_at'],
            (new TicketAssignment)->getFillable()
        );
    }

    public function test_assigned_at_is_cast_to_carbon(): void
    {
        $row = $this->assignment()->fresh();

        $this->assertInstanceOf(Carbon::class, $row->assigned_at);
        $this->assertSame('2026-06-15 10:00:00', $row->assigned_at->format('Y-m-d H:i:s'));
    }

    public function test_first_assignment_has_null_from_user(): void
    {
        $row = $this->assignment()->fresh();

        $this->assertNull($row->from_user);
        $this->assertNull($row->fromUser);
    }

    public function test_from_to_and_assigned_by_relations(): void
    {
        $from = User::factory()->agent()->create();
        $to = User::factory()->agent()->create();
        $by = User::factory()->admin()->create();
        $row = $this->assignment([
            'from_user' => $from->user_id, 'to_user' => $to->user_id, 'assigned_by' => $by->user_id,
        ]);

        $this->assertTrue($row->fromUser->is($from));
        $this->assertTrue($row->toUser->is($to));
        $this->assertTrue($row->assignedBy->is($by));
    }

    public function test_ticket_relation_returns_ticket(): void
    {
        $ticket = Ticket::factory()->create();
        $row = $this->assignment(['ticket_id' => $ticket->ticket_id]);

        $this->assertTrue($row->ticket->is($ticket));
    }

    public function test_ticket_relation_ignores_agent_visibility_scope(): void
    {
        $owner = User::factory()->agent()->create();
        $viewer = User::factory()->agent()->create();
        $ticket = Ticket::factory()->assignedTo($owner)->create();
        $row = $this->assignment(['ticket_id' => $ticket->ticket_id]);

        $this->app['auth']->forgetGuards();
        $this->actingAs($viewer);

        $this->assertNull(Ticket::find($ticket->ticket_id), 'sanity: the scope hides it');
        $this->assertTrue($row->fresh()->ticket->is($ticket));
    }
}
