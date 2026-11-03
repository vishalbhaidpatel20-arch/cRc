<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class EnsureUserIsActiveTest extends TestCase
{
    use RefreshDatabase;

    private Store $session;

    private function request(?User $user): Request
    {
        $this->session = app('session.store');
        $this->session->start();
        $request = Request::create('/x');
        $request->setLaravelSession($this->session);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function handle(Request $request): Response
    {
        return (new EnsureUserIsActive)->handle($request, fn () => new Response('passed'));
    }

    public function test_active_user_passes_through(): void
    {
        $user = User::factory()->create();

        $this->assertSame('passed', $this->handle($this->request($user))->getContent());
    }

    public function test_guest_passes_through(): void
    {
        $this->assertSame('passed', $this->handle($this->request(null))->getContent());
    }

    public function test_inactive_user_is_redirected_to_login(): void
    {
        $user = User::factory()->inactive()->create();

        $response = $this->handle($this->request($user));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('login'), $response->getTargetUrl());
    }

    public function test_inactive_user_gets_the_deactivated_message(): void
    {
        $user = User::factory()->inactive()->create();

        $this->handle($this->request($user));

        $this->assertSame('Your account has been deactivated.', $this->session->get('errors')->first('email'));
    }

    public function test_inactive_user_is_logged_out_and_session_invalidated(): void
    {
        $user = User::factory()->inactive()->create();
        $this->actingAs($user);
        $request = $this->request($user);
        $oldId = $this->session->getId();
        $this->session->put('marker', 'x');

        $this->handle($request);

        $this->assertGuest();
        $this->assertNotSame($oldId, $this->session->getId());
        $this->assertNull($this->session->get('marker'));
    }

    public function test_next_is_not_called_for_inactive_user(): void
    {
        $user = User::factory()->inactive()->create();
        $called = false;

        (new EnsureUserIsActive)->handle($this->request($user), function () use (&$called) {
            $called = true;

            return new Response;
        });

        $this->assertFalse($called);
    }
}
