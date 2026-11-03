<?php

namespace Tests\Feature\E2E;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOnboardingJourneyTest extends TestCase
{
    use JourneyHelpers;
    use RefreshDatabase;

    public function test_admin_onboards_a_restricted_agent_who_logs_in_and_is_kept_out_of_admin_areas(): void
    {
        $admin = $this->makeAdmin(['full_name' => 'Boss Admin']);
        $one = Company::create(['company_code' => 'J1', 'name' => 'Journey One']);
        $two = Company::create(['company_code' => 'J2', 'name' => 'Journey Two']);
        Company::create(['company_code' => 'J3', 'name' => 'Journey Three']);

        // Admin logs in and lands on the admin dashboard.
        $this->loginAs($admin->email)->assertOk()->assertSee('Admin dashboard')->assertSee('Boss Admin');
        $this->assertAuthenticatedAs($admin);

        // Opens Users and the create form, which lists active companies.
        $this->browse(route('admin.users.index'))->assertOk()->assertSee('+ New agent');
        $this->browse(route('admin.users.create'))->assertOk()
            ->assertSee('New agent')->assertSee('Journey One')->assertSee('Journey Three');

        // A bad submission is bounced back with validation errors and creates nothing.
        $this->from(route('admin.users.create'))->followingRedirects()
            ->post(route('admin.users.store'), [
                'full_name' => 'Rita Restricted', 'email' => 'rita@example.com',
                'password' => 'short', 'password_confirmation' => 'nope',
                'all_companies' => '0',
            ])->assertOk();
        $this->assertDatabaseMissing('users', ['email' => 'rita@example.com']);

        // Valid submission: restricted agent with two companies.
        $this->submit('POST', route('admin.users.store'), [
            'full_name' => 'Rita Restricted', 'email' => 'rita@example.com', 'phone' => '9000000001',
            'password' => 'temp-pass-123', 'password_confirmation' => 'temp-pass-123',
            'all_companies' => '0', 'companies' => [$one->company_id, $two->company_id],
            'role' => 'admin', // must be ignored
        ], route('admin.users.create'))
            ->assertOk()
            ->assertSee('Agent Rita Restricted created.')
            ->assertSee('rita@example.com')
            ->assertSee('Selected');

        $rita = User::where('email', 'rita@example.com')->firstOrFail();
        $this->assertSame('agent', $rita->role);
        $this->assertSame($admin->user_id, $rita->created_by);
        $this->assertEqualsCanonicalizing(
            [$one->company_id, $two->company_id],
            $rita->companies()->pluck('companies.company_id')->all()
        );

        // Admin logs out; protected pages now bounce to login.
        $this->logout()->assertOk()->assertSee('Log in');
        $this->assertGuest();

        // The wrong temp password fails, the right one works.
        $this->loginAs('rita@example.com', 'wrong-pass')->assertSee('These credentials do not match our records.');
        $this->assertGuest();
        $this->loginAs('rita@example.com', 'temp-pass-123')
            ->assertOk()->assertSee('Agent dashboard')->assertSee('Welcome, Rita Restricted');
        $this->assertAuthenticatedAs($rita);

        // Agent nav has no Users link; admin URLs are 403.
        $this->browse(route('agent.dashboard'))->assertDontSee('Users');
        $this->freshRequest()->get('/admin')->assertForbidden();
        $this->freshRequest()->get(route('admin.users.index'))->assertForbidden();
        $this->freshRequest()->get(route('admin.users.create'))->assertForbidden();
        $this->freshRequest()->post(route('admin.users.store'), [
            'full_name' => 'Sneaky', 'email' => 'sneaky@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123', 'all_companies' => '1',
        ])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);

        // The root URL sends the agent to the agent dashboard.
        $this->browse('/')->assertSee('Agent dashboard');

        $this->logout()->assertSee('Log in');
        $this->assertGuest();
    }

    public function test_logged_in_users_are_bounced_away_from_the_login_page(): void
    {
        $admin = $this->makeAdmin();
        $this->loginAs($admin->email);

        $this->browse('/login')->assertOk()->assertSee('Admin dashboard');
    }
}
