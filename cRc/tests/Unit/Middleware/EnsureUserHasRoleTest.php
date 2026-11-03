<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\EnsureUserHasRole;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class EnsureUserHasRoleTest extends TestCase
{
    private function pass(?string $userRole, string ...$allowed): Response
    {
        $request = Request::create('/x');
        $request->setUserResolver(fn () => $userRole === null ? null : new User(['role' => $userRole]));

        return (new EnsureUserHasRole)->handle($request, fn () => new Response('passed'), ...$allowed);
    }

    private function assertForbidden(callable $fn): void
    {
        try {
            $fn();
            $this->fail('Expected a 403.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_allows_a_listed_role(): void
    {
        $this->assertSame('passed', $this->pass('admin', 'admin')->getContent());
    }

    public function test_accepts_multiple_roles(): void
    {
        $this->assertSame('passed', $this->pass('agent', 'admin', 'agent')->getContent());
        $this->assertSame('passed', $this->pass('admin', 'admin', 'agent')->getContent());
    }

    public function test_aborts_403_for_an_unlisted_role(): void
    {
        $this->assertForbidden(fn () => $this->pass('agent', 'admin'));
    }

    public function test_aborts_403_for_no_user(): void
    {
        $this->assertForbidden(fn () => $this->pass(null, 'admin', 'agent'));
    }

    public function test_aborts_403_when_no_roles_are_listed(): void
    {
        $this->assertForbidden(fn () => $this->pass('admin'));
    }
}
