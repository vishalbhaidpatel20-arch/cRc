<?php

namespace Tests\Feature\E2E;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Milestone 3 journey: admin logs a client's request, assigns it, the agent sees only
 * their own work, URL guessing is denied, and reassignment moves access but keeps history.
 */
class TicketAssignmentJourneyTest extends TestCase
{
    use JourneyHelpers;
    use RefreshDatabase;

    public function test_admin_assigns_agent_sees_only_theirs_and_reassignment_moves_access(): void
    {
        $admin = $this->makeAdmin(['full_name' => 'Boss Admin']);
        $anna = $this->makeAgent(['full_name' => 'Anna Agent', 'email' => 'anna@example.com']);
        $ben = $this->makeAgent(['full_name' => 'Ben Agent', 'email' => 'ben@example.com']);
        $company = Company::factory()->create(['name' => 'Initech']);
        $peter = Contact::factory()->for($company)->create(['full_name' => 'Peter Gibbons']);
        $bystander = Ticket::factory()->assignedTo($ben)->create(['title' => 'Ben only: printer jam']);

        // 1. Admin logs the client's request. It starts in the unassigned queue.
        $this->loginAs($admin->email)->assertSee('Admin dashboard')->assertSee('Unassigned queue');
        $this->browse(route('tickets.create'))->assertOk()->assertSee('Peter Gibbons')->assertSee('Select a contact');
        $this->submit('POST', route('tickets.store'), [
            'contact_id' => $peter->contact_id, 'topic' => 'bug', 'priority' => 'critical',
            'title' => 'TPS report totals are wrong', 'description' => 'Cover sheet missing',
        ], route('tickets.create'))
            ->assertOk()->assertSee('created.')->assertSee('TPS report totals are wrong')->assertSee('Initech')->assertSee('Unassigned');

        $ticket = Ticket::where('title', 'TPS report totals are wrong')->firstOrFail();
        $this->assertNull($ticket->assigned_to);
        $this->browse(route('tickets.index', ['assigned' => 'unassigned']))->assertSee('TPS report totals are wrong')
            ->assertDontSee('Ben only: printer jam');

        // 2. Admin assigns it to Anna (admin-only action).
        $this->submit('POST', route('tickets.assign', $ticket), [
            'assigned_to' => $anna->user_id, 'reason' => 'Anna handles Initech',
        ], route('tickets.show', $ticket))
            ->assertOk()->assertSee('assigned to Anna Agent')->assertSee('Anna handles Initech')->assertSee('Reassign');
        $this->logout();

        // 3. Anna sees the alert, her ticket, and nothing of Ben's.
        $this->loginAs($anna->email)->assertSee('Agent dashboard')->assertSee('TPS report totals are wrong')
            ->assertSee('assigned by Boss Admin');
        $this->browse(route('tickets.index'))->assertSee('TPS report totals are wrong')->assertDontSee('Ben only: printer jam');
        $this->browse(route('tickets.show', $ticket))->assertOk()->assertDontSee('Select an agent');

        // 4. URL guessing is denied; agents cannot assign; nothing leaks through search.
        $this->freshRequest()->get(route('tickets.show', $bystander))->assertNotFound();
        $this->freshRequest()->get(route('tickets.edit', $bystander))->assertNotFound();
        $this->freshRequest()->post(route('tickets.assign', $ticket), ['assigned_to' => $ben->user_id])->assertForbidden();
        $this->browse(route('tickets.index', ['q' => 'printer', 'status' => 'all']))->assertDontSee('Ben only: printer jam');
        $this->assertSame($anna->user_id, $ticket->fresh()->assigned_to);

        // 5. Anna works her ticket.
        $this->submit('PUT', route('tickets.update', $ticket), [
            'topic' => 'bug', 'priority' => 'critical', 'status' => 'in_progress',
            'title' => 'TPS report totals are wrong', 'description' => 'Cover sheet missing',
        ], route('tickets.edit', $ticket))->assertOk()->assertSee('Ticket updated.')->assertSee('in progress');
        $this->logout();

        // 6. Admin reassigns to Ben. Anna loses access, Ben gains it, history keeps both.
        $this->loginAs($admin->email);
        $this->submit('POST', route('tickets.assign', $ticket), [
            'assigned_to' => $ben->user_id, 'reason' => 'Anna is out',
        ], route('tickets.show', $ticket))->assertOk()->assertSee('assigned to Ben Agent');
        $this->browse(route('tickets.show', $ticket))
            ->assertSee('Anna Agent')->assertSee('Ben Agent')->assertSee('Anna is out')->assertSee('Assignment history');
        $this->logout();

        $this->loginAs($anna->email);
        $this->freshRequest()->get(route('tickets.show', $ticket))->assertNotFound();
        $this->browse(route('tickets.index'))->assertDontSee('TPS report totals are wrong');
        $this->logout();

        $this->loginAs($ben->email)->assertSee('TPS report totals are wrong');
        $this->browse(route('tickets.show', $ticket))->assertOk()->assertSee('Peter Gibbons');
        $this->logout();

        $this->assertSame(2, $ticket->assignments()->count());
    }

    public function test_agent_logs_a_ticket_for_a_client_and_it_is_theirs(): void
    {
        $agent = $this->makeAgent(['full_name' => 'Solo Agent']);
        $contact = Contact::factory()->create(['full_name' => 'Samir Nagheenanajar']);

        $this->loginAs($agent->email);
        $this->browse(route('tickets.create', ['contact_id' => $contact->contact_id]))
            ->assertOk()->assertSee('will be assigned to you')->assertDontSee('Assign to');
        $this->submit('POST', route('tickets.store'), [
            'contact_id' => $contact->contact_id, 'topic' => 'query', 'priority' => 'low', 'title' => 'How do I export?',
        ], route('tickets.create'))->assertOk()->assertSee('How do I export?')->assertSee('Solo Agent');

        $this->browse(route('tickets.index'))->assertSee('How do I export?');
    }
}
