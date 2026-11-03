<?php

namespace Tests\Feature\E2E;

use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * Shared helpers for journey tests. Logging in goes through the real
 * POST /login so the session, regenerate() and middleware are all exercised.
 */
trait JourneyHelpers
{
    protected function makeAdmin(array $over = []): User
    {
        return User::factory()->admin()->create($over);
    }

    protected function makeAgent(array $over = []): User
    {
        return User::factory()->agent()->create($over);
    }

    /** Simulate the start of a new HTTP request (drop the guard's in-memory user). */
    protected function freshRequest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    /** Real session login; follows redirects and returns the landing page. */
    protected function loginAs(string $email, string $password = 'password'): TestResponse
    {
        $this->freshRequest();

        return $this->from(url('/login'))->followingRedirects()->post('/login', ['email' => $email, 'password' => $password]);
    }

    protected function logout(): TestResponse
    {
        $this->freshRequest();

        return $this->followingRedirects()->post('/logout');
    }

    /** Follow-redirect GET. */
    protected function browse(string $url): TestResponse
    {
        $this->freshRequest();

        return $this->followingRedirects()->get($url);
    }

    /** POST/PUT/PATCH coming from a page (so back() redirects work), redirects followed. */
    protected function submit(string $method, string $url, array $data = [], ?string $from = null): TestResponse
    {
        $this->freshRequest();

        return $this->from($from ?? url('/'))->followingRedirects()->call($method, $url, $data);
    }
}
