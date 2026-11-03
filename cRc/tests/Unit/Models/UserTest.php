<?php

namespace Tests\Unit\Models;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_constants(): void
    {
        $this->assertSame('admin', User::ROLE_ADMIN);
        $this->assertSame('agent', User::ROLE_AGENT);
    }

    public function test_admin_role_helpers(): void
    {
        $user = User::factory()->admin()->make();

        $this->assertTrue($user->isAdmin());
        $this->assertFalse($user->isAgent());
    }

    public function test_agent_role_helpers(): void
    {
        $user = User::factory()->agent()->make();

        $this->assertTrue($user->isAgent());
        $this->assertFalse($user->isAdmin());
    }

    public function test_admin_home_route(): void
    {
        $this->assertSame('admin.dashboard', User::factory()->admin()->make()->homeRoute());
    }

    public function test_agent_home_route(): void
    {
        $this->assertSame('agent.dashboard', User::factory()->agent()->make()->homeRoute());
    }

    public function test_auth_password_is_the_stored_hash(): void
    {
        $user = User::factory()->create();

        $this->assertNotEmpty($user->getAuthPassword());
        $this->assertSame($user->password_hash, $user->getAuthPassword());
        $this->assertTrue(Hash::check('password', $user->getAuthPassword()));
    }

    public function test_auth_password_is_null_for_invited_user(): void
    {
        $user = User::factory()->invited()->create();

        $this->assertNull($user->fresh()->getAuthPassword());
    }

    public function test_remember_token_name_is_empty(): void
    {
        $this->assertSame('', (new User)->getRememberTokenName());
    }

    public function test_password_is_hashed_on_assignment(): void
    {
        $user = new User;
        $user->password_hash = 'plain-secret';

        $this->assertNotSame('plain-secret', $user->password_hash);
        $this->assertTrue(Hash::check('plain-secret', $user->password_hash));
    }

    public function test_password_is_not_stored_in_plain_text(): void
    {
        $user = User::factory()->create(['password_hash' => 'plain-secret']);

        $stored = User::query()->whereKey($user->user_id)->value('password_hash');

        $this->assertNotSame('plain-secret', $stored);
        $this->assertTrue(Hash::check('plain-secret', $stored));
    }

    public function test_password_hash_is_hidden_from_array_and_json(): void
    {
        $user = User::factory()->create();

        $this->assertArrayNotHasKey('password_hash', $user->toArray());
        $this->assertStringNotContainsString('password_hash', $user->toJson());
    }

    public function test_flags_are_cast_to_booleans(): void
    {
        $user = User::factory()->create(['all_companies' => 0, 'is_active' => 1])->fresh();

        $this->assertFalse($user->all_companies);
        $this->assertTrue($user->is_active);
    }

    public function test_created_at_is_cast_to_carbon(): void
    {
        $user = User::factory()->create()->fresh();

        $this->assertInstanceOf(Carbon::class, $user->created_at);
    }

    public function test_user_id_is_not_mass_assignable(): void
    {
        $user = new User;
        $user->fill(['user_id' => 999, 'full_name' => 'Fill Test']);

        $this->assertNull($user->user_id);
        $this->assertSame('Fill Test', $user->full_name);
    }

    public function test_companies_relation_attaches_and_reads_through_pivot(): void
    {
        $user = User::factory()->create(['all_companies' => false]);
        $a = Company::factory()->create();
        $b = Company::factory()->create();
        Company::factory()->create();

        $user->companies()->attach([$a->company_id, $b->company_id]);

        $this->assertEqualsCanonicalizing(
            [$a->company_id, $b->company_id],
            $user->companies()->pluck('companies.company_id')->all()
        );
        $this->assertDatabaseCount('user_companies', 2);
    }

    public function test_companies_relation_uses_user_companies_table(): void
    {
        $this->assertSame('user_companies', (new User)->companies()->getTable());
    }
}
