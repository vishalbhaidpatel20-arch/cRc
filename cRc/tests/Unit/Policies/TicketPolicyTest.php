<?php

namespace Tests\Unit\Policies;

use App\Models\Ticket;
use App\Models\User;
use App\Policies\TicketPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TicketPolicyTest extends TestCase
{
    use RefreshDatabase;

    private TicketPolicy $policy;

    private User $admin;

    private User $agent;

    private User $otherAgent;

    private Ticket $assigned;

    private Ticket $unassigned;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new TicketPolicy;
        $this->admin = User::factory()->admin()->create();
        $this->agent = User::factory()->agent()->create();
        $this->otherAgent = User::factory()->agent()->create();
        $this->assigned = Ticket::factory()->assignedTo($this->agent)->create();
        $this->unassigned = Ticket::factory()->unassigned()->create();
    }

    // ---- direct calls ----

    public function test_view_any_is_allowed_for_everyone(): void
    {
        $this->assertTrue($this->policy->viewAny($this->admin));
        $this->assertTrue($this->policy->viewAny($this->agent));
    }

    public function test_create_is_allowed_for_everyone(): void
    {
        $this->assertTrue($this->policy->create($this->admin));
        $this->assertTrue($this->policy->create($this->agent));
    }

    public function test_admin_can_view_and_update_any_ticket(): void
    {
        foreach ([$this->assigned, $this->unassigned] as $ticket) {
            $this->assertTrue($this->policy->view($this->admin, $ticket));
            $this->assertTrue($this->policy->update($this->admin, $ticket));
        }
    }

    public function test_assigned_agent_can_view_and_update_own_ticket(): void
    {
        $this->assertTrue($this->policy->view($this->agent, $this->assigned));
        $this->assertTrue($this->policy->update($this->agent, $this->assigned));
    }

    public function test_other_agent_cannot_view_or_update_ticket(): void
    {
        $this->assertFalse($this->policy->view($this->otherAgent, $this->assigned));
        $this->assertFalse($this->policy->update($this->otherAgent, $this->assigned));
    }

    public function test_agent_cannot_view_or_update_unassigned_ticket(): void
    {
        $this->assertFalse($this->policy->view($this->agent, $this->unassigned));
        $this->assertFalse($this->policy->update($this->agent, $this->unassigned));
    }

    public function test_only_admin_can_assign(): void
    {
        $this->assertTrue($this->policy->assign($this->admin, $this->assigned));
        $this->assertTrue($this->policy->assign($this->admin, $this->unassigned));
        $this->assertFalse($this->policy->assign($this->agent, $this->assigned));
        $this->assertFalse($this->policy->assign($this->otherAgent, $this->unassigned));
    }

    // ---- through the Gate ----

    public function test_gate_view_any_and_create(): void
    {
        $this->assertTrue(Gate::forUser($this->agent)->allows('viewAny', Ticket::class));
        $this->assertTrue(Gate::forUser($this->agent)->allows('create', Ticket::class));
        $this->assertTrue(Gate::forUser($this->admin)->allows('create', Ticket::class));
    }

    public function test_gate_view_and_update_for_each_actor(): void
    {
        foreach (['view', 'update'] as $ability) {
            $this->assertTrue(Gate::forUser($this->admin)->allows($ability, $this->unassigned), "admin $ability unassigned");
            $this->assertTrue(Gate::forUser($this->admin)->allows($ability, $this->assigned), "admin $ability assigned");
            $this->assertTrue(Gate::forUser($this->agent)->allows($ability, $this->assigned), "owner $ability");
            $this->assertTrue(Gate::forUser($this->otherAgent)->denies($ability, $this->assigned), "other $ability");
            $this->assertTrue(Gate::forUser($this->agent)->denies($ability, $this->unassigned), "agent $ability unassigned");
        }
    }

    public function test_gate_assign_is_admin_only(): void
    {
        $this->assertTrue(Gate::forUser($this->admin)->allows('assign', $this->assigned));
        $this->assertTrue(Gate::forUser($this->agent)->denies('assign', $this->assigned));
        $this->assertTrue(Gate::forUser($this->otherAgent)->denies('assign', $this->assigned));
    }
}
