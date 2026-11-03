<?php

namespace Tests\Unit\Factories;

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_default_is_an_active_agent_with_all_companies(): void
    {
        $user = User::factory()->create()->fresh();

        $this->assertSame('agent', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->all_companies);
    }

    public function test_user_admin_state(): void
    {
        $this->assertSame('admin', User::factory()->admin()->create()->role);
    }

    public function test_user_agent_state(): void
    {
        $this->assertSame('agent', User::factory()->admin()->agent()->create()->role);
    }

    public function test_user_inactive_state(): void
    {
        $this->assertFalse(User::factory()->inactive()->create()->fresh()->is_active);
    }

    public function test_user_invited_state_has_no_password(): void
    {
        $this->assertNull(User::factory()->invited()->create()->fresh()->password_hash);
    }

    public function test_user_default_password_is_password(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(Hash::check('password', $user->password_hash));
        $this->assertFalse(Hash::check('wrong', $user->password_hash));
    }

    public function test_user_emails_are_unique_across_many_creates(): void
    {
        User::factory()->count(30)->create();

        $this->assertSame(30, User::query()->distinct()->count('email'));
    }

    public function test_company_default_is_active_and_inactive_state(): void
    {
        $this->assertTrue(Company::factory()->create()->fresh()->is_active);
        $this->assertFalse(Company::factory()->inactive()->create()->fresh()->is_active);
    }

    public function test_company_codes_and_names_are_unique(): void
    {
        Company::factory()->count(20)->create();

        $this->assertSame(20, Company::query()->distinct()->count('company_code'));
        $this->assertSame(20, Company::query()->distinct()->count('name'));
    }

    public function test_contact_factory_creates_a_company(): void
    {
        $contact = Contact::factory()->create();

        $this->assertNotNull($contact->company);
        $this->assertDatabaseCount('companies', 1);
    }

    public function test_contact_defaults_and_inactive_state(): void
    {
        $this->assertTrue(Contact::factory()->create()->fresh()->is_active);
        $this->assertFalse(Contact::factory()->inactive()->create()->fresh()->is_active);
    }

    public function test_contact_emails_are_unique_across_many_creates(): void
    {
        Contact::factory()->count(20)->create();

        $this->assertSame(20, Contact::query()->distinct()->count('email'));
    }
}
