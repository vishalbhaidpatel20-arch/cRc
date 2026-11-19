<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/** Horizon (/horizon) is for active admins only. It runs in production on Redis; tests only check the gate and route. */
class HorizonDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_gate_allows_only_active_admins(): void
    {
        $this->assertTrue(Gate::forUser(User::factory()->admin()->create())->allows('viewHorizon'));
        $this->assertFalse(Gate::forUser(User::factory()->agent()->create())->allows('viewHorizon'));
        $this->assertFalse(Gate::forUser(User::factory()->admin()->create(['is_active' => false]))->allows('viewHorizon'));
        $this->assertFalse(Gate::allows('viewHorizon'));
    }

    public function test_dashboard_is_closed_to_guests_and_agents_and_open_to_admins(): void
    {
        $this->get('/horizon')->assertForbidden();

        $this->actingAs(User::factory()->agent()->create())->get('/horizon')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->actingAs(User::factory()->admin()->create())->get('/horizon')->assertOk();
    }
}
