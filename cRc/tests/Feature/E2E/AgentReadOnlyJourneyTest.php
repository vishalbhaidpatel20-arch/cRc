<?php

namespace Tests\Feature\E2E;

use App\Models\Company;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AgentReadOnlyJourneyTest extends TestCase
{
    use JourneyHelpers;
    use RefreshDatabase;

    private function snapshot(): array
    {
        return [
            'companies' => DB::table('companies')->orderBy('company_id')->get()->toArray(),
            'contacts' => DB::table('contacts')->orderBy('contact_id')->get()->toArray(),
            'users' => DB::table('users')->count(),
        ];
    }

    public function test_agent_browses_and_searches_but_cannot_change_anything(): void
    {
        $agent = $this->makeAgent(['full_name' => 'Read Only Rae']);
        $acme = Company::factory()->create(['name' => 'Acme Corp', 'city' => 'Nashik']);
        $hooli = Company::factory()->create(['name' => 'Hooli Inc', 'city' => 'Vapi']);
        $carol = Contact::factory()->for($acme)->create([
            'full_name' => 'Carol Clark', 'email' => 'carol@acme.example', 'phone' => '9700000001',
        ]);
        Contact::factory()->for($hooli)->create(['full_name' => 'Dan Dixon', 'phone' => '9700000002']);

        $this->loginAs($agent->email)->assertSee('Agent dashboard')->assertSee('Welcome, Read Only Rae');

        // Browse companies.
        $this->browse(route('companies.index'))->assertOk()
            ->assertSee('Acme Corp')->assertSee('Hooli Inc')
            ->assertDontSee('+ New company')->assertDontSee('Users');
        $this->browse(route('companies.index', ['q' => 'Vapi']))
            ->assertSee('Hooli Inc')->assertDontSee('Acme Corp');

        // Company detail shows contacts but no admin buttons.
        $this->browse(route('companies.show', $acme))->assertOk()
            ->assertSee('Carol Clark')
            ->assertDontSee('Deactivate')->assertDontSee('+ Add contact')
            ->assertDontSee(route('companies.edit', $acme));

        // Browse and search contacts, open one.
        $this->browse(route('contacts.index'))->assertSee('Carol Clark')->assertSee('Dan Dixon');
        $this->browse(route('contacts.index', ['q' => '9700000002']))
            ->assertSee('Dan Dixon')->assertDontSee('Carol Clark');
        $this->browse(route('contacts.index', ['q' => 'Acme']))
            ->assertSee('Carol Clark')->assertDontSee('Dan Dixon');
        $this->browse(route('contacts.show', $carol))->assertOk()
            ->assertSee('carol@acme.example')->assertSee('Acme Corp')
            ->assertDontSee('Deactivate')->assertDontSee(route('contacts.edit', $carol));

        // Every admin URL is forbidden and the database stays untouched.
        $before = $this->snapshot();
        $payload = [
            'company_id' => $acme->company_id, 'full_name' => 'Evil', 'email' => 'evil@example.com',
            'product_role' => 'end_user', 'preferred_channel' => 'call', 'name' => 'Evil Co',
        ];

        $this->freshRequest()->get(route('companies.create'))->assertForbidden();
        $this->freshRequest()->post(route('companies.store'), $payload)->assertForbidden();
        $this->freshRequest()->get(route('companies.edit', $acme))->assertForbidden();
        $this->freshRequest()->put(route('companies.update', $acme), $payload)->assertForbidden();
        $this->freshRequest()->patch(route('companies.active', $acme))->assertForbidden();
        $this->freshRequest()->get(route('contacts.create'))->assertForbidden();
        $this->freshRequest()->post(route('contacts.store'), $payload)->assertForbidden();
        $this->freshRequest()->get(route('contacts.edit', $carol))->assertForbidden();
        $this->freshRequest()->put(route('contacts.update', $carol), $payload)->assertForbidden();
        $this->freshRequest()->patch(route('contacts.active', $carol))->assertForbidden();
        $this->freshRequest()->get('/admin')->assertForbidden();
        $this->freshRequest()->get(route('admin.users.index'))->assertForbidden();
        $this->freshRequest()->get(route('admin.users.create'))->assertForbidden();
        $this->freshRequest()->post(route('admin.users.store'), $payload)->assertForbidden();

        $this->assertEquals($before, $this->snapshot());

        // Still logged in after all the 403s, and can log out cleanly.
        $this->browse(route('contacts.index'))->assertOk()->assertSee('Carol Clark');
        $this->logout()->assertSee('Log in');
        $this->browse(route('contacts.index'))->assertSee('Log in');
    }

    public function test_restricted_agent_still_sees_every_company_and_contact(): void
    {
        $mine = Company::factory()->create(['name' => 'Assigned Co']);
        $other = Company::factory()->create(['name' => 'Unassigned Co']);
        Contact::factory()->for($mine)->create(['full_name' => 'Assigned Person']);
        Contact::factory()->for($other)->create(['full_name' => 'Unassigned Person']);

        $agent = $this->makeAgent(['all_companies' => false]);
        $agent->companies()->sync([$mine->company_id]);

        $this->loginAs($agent->email)->assertSee('Agent dashboard');

        $this->browse(route('companies.index'))->assertSee('Assigned Co')->assertSee('Unassigned Co');
        $this->browse(route('companies.show', $other))->assertOk()->assertSee('Unassigned Person');
        $this->browse(route('contacts.index'))->assertSee('Assigned Person')->assertSee('Unassigned Person');
        $this->browse(route('contacts.index', ['company_id' => $other->company_id]))
            ->assertSee('Unassigned Person')->assertDontSee('Assigned Person');
        $this->browse(route('contacts.index', ['q' => 'Unassigned']))->assertSee('Unassigned Person');
    }
}
