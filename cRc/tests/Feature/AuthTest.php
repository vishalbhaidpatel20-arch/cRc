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

    public function test_the_login_page_is_a_split_screen_with_a_brand_panel_and_a_polished_form(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        // Brand panel and the welcome form.
        $this->assertStringContainsString('class="login-shell"', $html);
        $this->assertStringContainsString('Every client conversation,', $html);
        $this->assertStringContainsString('Welcome back', $html);

        // The form: both fields with their autocomplete hints, the show/hide password toggle, a posting form that is not sent through partial refresh.
        $this->assertMatchesRegularExpression('/<form method="POST" action="[^"]*\/login" data-no-ajax>/', $html);
        $this->assertStringContainsString('name="email"', $html);
        $this->assertStringContainsString('autocomplete="current-password"', $html);
        $this->assertStringContainsString('data-toggle-password', $html);
        $this->assertStringContainsString('Log in</button>', $html);

        // No public sign-up, and the page tells people so.
        $this->assertStringContainsString('There is no public sign-up.', $html);
        $this->assertStringNotContainsString('/register', $html);

        // Small screens: the responsive rule comes after the base rules, so it wins.
        $this->assertGreaterThan(strpos($html, '.login-brand{position:relative'), strpos($html, '@media (max-width:900px)'));
    }

    public function test_the_login_error_is_shown_inside_the_form_with_the_field_marked_invalid(): void
    {
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');

        $html = $this->followingRedirects()->from('/login')->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])->getContent();

        $this->assertStringContainsString('These credentials do not match our records.', $html);
        $this->assertMatchesRegularExpression('/class="lg-field\s+invalid\s*"/', $html);
        $this->assertStringContainsString('value="nobody@example.com"', $html, 'the typed email is kept');
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
