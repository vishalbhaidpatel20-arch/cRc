<?php

namespace Tests\Unit\Notifications;

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketAssignedTest extends TestCase
{
    use RefreshDatabase;

    private function ticket(User $agent): Ticket
    {
        return Ticket::factory()->assignedTo($agent)->priority('high')->create([
            'ticket_no' => 'TKT-N0001',
            'title' => 'Printer on fire',
        ]);
    }

    public function test_via_is_database_only(): void
    {
        $agent = User::factory()->agent()->create();
        $n = new TicketAssigned($this->ticket($agent), User::factory()->admin()->create());

        $this->assertSame(['database'], $n->via($agent));
    }

    public function test_to_array_has_expected_shape_and_values_without_reason(): void
    {
        $agent = User::factory()->agent()->create();
        $admin = User::factory()->admin()->create(['full_name' => 'Ada Admin']);
        $ticket = $this->ticket($agent);

        $data = (new TicketAssigned($ticket, $admin))->toArray($agent);

        $this->assertSame(
            ['ticket_id', 'ticket_no', 'title', 'priority', 'assigned_by', 'reason'],
            array_keys($data)
        );
        $this->assertSame($ticket->ticket_id, $data['ticket_id']);
        $this->assertSame('TKT-N0001', $data['ticket_no']);
        $this->assertSame('Printer on fire', $data['title']);
        $this->assertSame('high', $data['priority']);
        $this->assertSame('Ada Admin', $data['assigned_by']);
        $this->assertNull($data['reason']);
    }

    public function test_to_array_includes_reason_when_given(): void
    {
        $agent = User::factory()->agent()->create();
        $admin = User::factory()->admin()->create();

        $data = (new TicketAssigned($this->ticket($agent), $admin, 'Covering for Bob'))->toArray($agent);

        $this->assertSame('Covering for Bob', $data['reason']);
    }

    public function test_sending_stores_one_unread_database_notification(): void
    {
        $agent = User::factory()->agent()->create();
        $admin = User::factory()->admin()->create(['full_name' => 'Ada Admin']);
        $ticket = $this->ticket($agent);

        $agent->notify(new TicketAssigned($ticket, $admin, 'Urgent'));

        $this->assertDatabaseCount('notifications', 1);
        $row = $agent->notifications()->first();
        $this->assertSame(TicketAssigned::class, $row->type);
        $this->assertNull($row->read_at);
        $this->assertSame(User::class, $row->notifiable_type);
        $this->assertSame($agent->user_id, (int) $row->notifiable_id);
        $this->assertSame([
            'ticket_id' => $ticket->ticket_id,
            'ticket_no' => 'TKT-N0001',
            'title' => 'Printer on fire',
            'priority' => 'high',
            'assigned_by' => 'Ada Admin',
            'reason' => 'Urgent',
        ], $row->data);
    }

    public function test_notification_is_only_visible_to_the_recipient(): void
    {
        $agent = User::factory()->agent()->create();
        $other = User::factory()->agent()->create();

        $agent->notify(new TicketAssigned($this->ticket($agent), User::factory()->admin()->create()));

        $this->assertCount(1, $agent->unreadNotifications);
        $this->assertCount(0, $other->notifications);
    }
}
