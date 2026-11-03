<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $over = []): array
    {
        return array_merge([
            'full_name' => 'New Agent',
            'email' => 'new.agent@example.com',
            'phone' => '9000000099',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
            'all_companies' => '1',
        ], $over);
    }

    public function test_there_is_no_public_registration(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', $this->payload())->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'new.agent@example.com']);
    }

    public function test_guest_cannot_create_users(): void
    {
        $this->get(route('admin.users.create'))->assertRedirect(route('login'));
        $this->post(route('admin.users.store'), $this->payload())->assertRedirect(route('login'));
        $this->assertDatabaseMissing('users', ['email' => 'new.agent@example.com']);
    }

    public function test_agent_cannot_create_users(): void
    {
        $this->actingAs(User::factory()->agent()->create());

        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('admin.users.create'))->assertForbidden();
        $this->post(route('admin.users.store'), $this->payload())->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'new.agent@example.com']);
    }

    public function test_admin_can_create_an_agent_who_can_then_log_in(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), $this->payload())
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'new.agent@example.com',
            'role' => 'agent',
            'is_active' => 1,
            'all_companies' => 1,
            'created_by' => $admin->user_id,
        ]);

        auth()->logout();
        $this->post('/login', ['email' => 'new.agent@example.com', 'password' => 'secret-pass-1'])
            ->assertRedirect(route('agent.dashboard'));
    }

    public function test_role_cannot_be_chosen_through_the_request(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), $this->payload(['role' => 'admin']));

        $this->assertDatabaseHas('users', ['email' => 'new.agent@example.com', 'role' => 'agent']);
    }

    public function test_password_is_stored_hashed(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), $this->payload());

        $stored = User::where('email', 'new.agent@example.com')->value('password_hash');
        $this->assertNotSame('secret-pass-1', $stored);
        $this->assertTrue(password_verify('secret-pass-1', $stored));
    }

    public function test_restricted_agent_gets_selected_companies(): void
    {
        $a = Company::create(['company_code' => 'T1', 'name' => 'T One']);
        $b = Company::create(['company_code' => 'T2', 'name' => 'T Two']);
        Company::create(['company_code' => 'T3', 'name' => 'T Three']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), $this->payload([
                'all_companies' => '0',
                'companies' => [$a->company_id, $b->company_id],
            ]))
            ->assertRedirect(route('admin.users.index'));

        $agent = User::where('email', 'new.agent@example.com')->first();
        $this->assertFalse($agent->all_companies);
        $this->assertEqualsCanonicalizing(
            [$a->company_id, $b->company_id],
            $agent->companies()->pluck('companies.company_id')->all()
        );
    }

    public function test_restricted_agent_needs_at_least_one_company(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), $this->payload(['all_companies' => '0']))
            ->assertSessionHasErrors('companies');

        $this->assertDatabaseMissing('users', ['email' => 'new.agent@example.com']);
    }

    public function test_validation_rejects_duplicate_email_and_weak_password(): void
    {
        $existing = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), $this->payload([
                'email' => $existing->email,
                'password' => 'short',
                'password_confirmation' => 'short',
            ]))
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_admin_sees_the_user_list_and_form(): void
    {
        User::factory()->agent()->create(['full_name' => 'Listed Agent']);

        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.users.index'))->assertOk()->assertSee('Listed Agent');
        $this->get(route('admin.users.create'))->assertOk()->assertSee('New agent');
    }
}
