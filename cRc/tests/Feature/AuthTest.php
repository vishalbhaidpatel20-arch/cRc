<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_shown_to_guests(): void
    {
        $this->get('/login')->assertOk()->assertSee('Log in');
    }

    public function test_root_redirects_guests_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_admin_logs_in_and_lands_on_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_agent_logs_in_and_lands_on_agent_dashboard(): void
    {
        $agent = User::factory()->agent()->create();

        $this->post('/login', ['email' => $agent->email, 'password' => 'password'])
            ->assertRedirect(route('agent.dashboard'));

        $this->assertAuthenticatedAs($agent);
    }

    public function test_login_always_starts_on_the_dashboard_even_after_being_sent_away_from_another_page(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->agent()->create();

        foreach ([[$admin, 'admin.dashboard'], [$agent, 'agent.dashboard']] as [$user, $dashboard]) {
            // A guest opens a protected page first (an old link, an expired session): Laravel remembers it as "intended".
            $this->get('/tickets')->assertRedirect(route('login'));
            $this->assertSame(url('/tickets'), session('url.intended'));

            $this->post('/login', ['email' => $user->email, 'password' => 'password'])
                ->assertRedirect(route($dashboard));
            $this->assertNull(session('url.intended'), 'the remembered page is dropped');

            $this->post('/logout');
            $this->app['auth']->forgetGuards();
        }
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_unknown_email_gets_the_same_error_as_wrong_password(): void
    {
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_invited_user_without_password_cannot_log_in(): void
    {
        $user = User::factory()->invited()->create();

        $this->post('/login', ['email' => $user->email, 'password' => ''])
            ->assertSessionHasErrors('password');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_deactivated_mid_session_is_logged_out(): void
    {
        $user = User::factory()->agent()->create();
        $this->actingAs($user);

        $user->update(['is_active' => false]);

        $this->get(route('agent.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_agent_cannot_open_admin_pages(): void
    {
        $this->actingAs(User::factory()->agent()->create())
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_admin_cannot_open_agent_pages(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('agent.dashboard'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_dashboards(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('agent.dashboard'))->assertRedirect(route('login'));
    }

    public function test_logged_in_user_visiting_login_goes_to_own_dashboard(): void
    {
        $this->actingAs(User::factory()->agent()->create())
            ->get('/login')
            ->assertRedirect(route('agent.dashboard'));
    }

    public function test_logout_ends_the_session(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'x@example.com', 'password' => 'bad']);
        }

        $this->post('/login', ['email' => 'x@example.com', 'password' => 'bad'])
            ->assertStatus(429);
    }
}
