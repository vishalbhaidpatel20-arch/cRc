<?php

namespace Tests\Feature\E2E;

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityJourneyTest extends TestCase
{
    use JourneyHelpers;
    use RefreshDatabase;

    /** Every protected route from `php artisan route:list` as [method, uri]. */
    private function protectedRoutes(): array
    {
        return [
            ['GET', '/admin'],
            ['GET', '/admin/users'],
            ['GET', '/admin/users/create'],
            ['POST', '/admin/users'],
            ['GET', '/admin/modules'],
            ['POST', '/admin/modules'],
            ['PUT', '/admin/modules/1'],
            ['PATCH', '/follow-ups/1/done'],
            ['GET', '/agent'],
            ['GET', '/companies'],
            ['GET', '/companies/create'],
            ['POST', '/companies'],
            ['GET', '/companies/1'],
            ['GET', '/companies/1/edit'],
            ['PUT', '/companies/1'],
            ['PATCH', '/companies/1/active'],
            ['GET', '/contacts'],
            ['GET', '/contacts/create'],
            ['POST', '/contacts'],
            ['GET', '/contacts/1'],
            ['GET', '/contacts/1/edit'],
            ['PUT', '/contacts/1'],
            ['PATCH', '/contacts/1/active'],
            ['GET', '/tickets'],
            ['GET', '/tickets/create'],
            ['POST', '/tickets'],
            ['GET', '/tickets/1'],
            ['GET', '/tickets/1/edit'],
            ['PUT', '/tickets/1'],
            ['POST', '/tickets/1/assign'],
            ['POST', '/notifications/read'],
            ['GET', '/interactions'],
            ['POST', '/tickets/1/interactions'],
            ['POST', '/contacts/1/interactions'],
            ['GET', '/templates'],
            ['GET', '/templates/create'],
            ['POST', '/templates'],
            ['GET', '/templates/1/edit'],
            ['PUT', '/templates/1'],
            ['PATCH', '/templates/1/active'],
            ['POST', '/logout'],
        ];
    }

    public function test_guest_is_redirected_to_login_from_every_protected_url(): void
    {
        $company = Company::factory()->create();
        Contact::factory()->for($company)->create();

        foreach ($this->protectedRoutes() as [$method, $uri]) {
            $this->freshRequest()->call($method, $uri)
                ->assertRedirect(route('login'), "{$method} {$uri} must redirect guests to /login");
        }

        // Following the redirect ends on the login form.
        $this->browse('/companies')->assertSee('Log in');
        $this->browse('/')->assertSee('Log in');
        $this->assertGuest();
    }

    public function test_every_registered_route_is_either_public_or_in_the_protected_list(): void
    {
        $public = ['/', 'login'];
        $listed = array_map(fn ($r) => ltrim($r[1], '/'), $this->protectedRoutes());

        foreach (app('router')->getRoutes() as $route) {
            $uri = $route->uri();
            if (str_starts_with($uri, 'up') || str_starts_with($uri, 'storage') || str_starts_with($uri, '_')) {
                continue;
            }
            $normalized = preg_replace('/\{[^}]+\}/', '1', $uri);
            $this->assertTrue(
                in_array($uri, $public, true) || in_array($normalized, $listed, true),
                "Route [{$uri}] is not covered by the guest-redirect list; add it."
            );
        }
    }

    public function test_there_is_no_registration_flow_even_when_logged_in(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['full_name' => 'X', 'email' => 'x@example.com', 'password' => 'password123'])
            ->assertNotFound();

        $admin = $this->makeAdmin();
        $this->loginAs($admin->email);
        $this->freshRequest()->get('/register')->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
    }

    public function test_deactivated_agent_is_logged_out_mid_session_and_cannot_log_in_again(): void
    {
        $admin = $this->makeAdmin();
        $agent = $this->makeAgent(['email' => 'target@example.com', 'full_name' => 'Target Agent']);
        Company::factory()->create(['name' => 'Some Co']);

        // The agent is working normally.
        $this->loginAs($agent->email)->assertSee('Target Agent');
        $this->browse(route('companies.index'))->assertOk()->assertSee('Some Co');
        $this->assertAuthenticatedAs($agent);

        // An admin deactivates the account (no UI yet in this milestone, so flip the flag directly).
        $agent->forceFill(['is_active' => false])->save();

        // The agent's existing session dies on the very next request.
        $this->browse(route('companies.index'))
            ->assertSee('Log in')
            ->assertSee('Your account has been deactivated.')
            ->assertDontSee('Some Co');
        $this->assertGuest();

        // Subsequent protected requests also bounce, and a fresh login is refused.
        $this->browse(route('agent.dashboard'))->assertSee('Log in');
        $this->loginAs('target@example.com')->assertSee('These credentials do not match our records.');
        $this->assertGuest();

        // Reactivation restores access, and the admin's own session was never affected.
        $agent->forceFill(['is_active' => true])->save();
        $this->loginAs('target@example.com')->assertSee('Agent dashboard');
        $this->logout();
        $this->loginAs($admin->email)->assertSee('Admin dashboard');
    }

    public function test_invited_users_without_a_password_cannot_log_in(): void
    {
        $invited = User::factory()->agent()->invited()->create(['email' => 'invited@example.com']);

        foreach (['', 'password', 'anything-else'] as $pw) {
            $this->loginAs($invited->email, $pw);
            $this->assertGuest();
        }
    }

    public function test_login_is_throttled_after_five_bad_attempts(): void
    {
        $user = $this->makeAgent(['email' => 'victim@example.com']);

        for ($i = 1; $i <= 5; $i++) {
            $this->freshRequest()->from('/login')
                ->post('/login', ['email' => $user->email, 'password' => "wrong-{$i}"])
                ->assertRedirect('/login')
                ->assertSessionHasErrors('email');
        }

        // 6th attempt is blocked, even with the correct password.
        $this->freshRequest()->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(429);
        $this->assertGuest();
    }

    public function test_wrong_role_gets_403_in_both_directions_and_right_role_recovers(): void
    {
        $admin = $this->makeAdmin();
        $agent = $this->makeAgent();

        $this->loginAs($agent->email)->assertSee('Agent dashboard');
        $this->freshRequest()->get('/admin')->assertForbidden();
        $this->logout();

        $this->loginAs($admin->email)->assertSee('Admin dashboard');
        $this->freshRequest()->get('/agent')->assertForbidden();
        $this->browse('/admin')->assertOk()->assertSee('Admin dashboard');
    }

    public function test_logout_really_ends_the_session(): void
    {
        $agent = $this->makeAgent();
        $this->loginAs($agent->email)->assertSee('Agent dashboard');
        $this->logout()->assertSee('Log in');

        $this->assertGuest();
        $this->freshRequest()->get('/agent')->assertRedirect(route('login'));
        $this->freshRequest()->get('/companies')->assertRedirect(route('login'));
    }
}
