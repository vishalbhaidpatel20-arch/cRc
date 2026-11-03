<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyContactTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function agent(array $state = []): User
    {
        return User::factory()->agent()->create($state);
    }

    private function contactPayload(Company $company, array $over = []): array
    {
        return array_merge([
            'company_id' => $company->company_id,
            'full_name' => 'Test Person',
            'email' => 'test.person@example.com',
            'phone' => '9811111111',
            'product_role' => 'end_user',
            'preferred_channel' => 'call',
        ], $over);
    }

    // ---- access ----------------------------------------------------------

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('companies.index'))->assertRedirect(route('login'));
        $this->get(route('contacts.index'))->assertRedirect(route('login'));
        $this->get(route('companies.create'))->assertRedirect(route('login'));
    }

    public function test_agents_can_view_all_companies_and_contacts(): void
    {
        $company = Company::factory()->create(['name' => 'Visible Co']);
        $contact = Contact::factory()->for($company)->create(['full_name' => 'Visible Person']);
        $restricted = $this->agent(['all_companies' => false]);

        $this->actingAs($restricted);
        $this->get(route('companies.index'))->assertOk()->assertSee('Visible Co');
        $this->get(route('companies.show', $company))->assertOk()->assertSee('Visible Person');
        $this->get(route('contacts.index'))->assertOk()->assertSee('Visible Person');
        $this->get(route('contacts.show', $contact))->assertOk()->assertSee($contact->email);
    }

    public function test_agents_cannot_create_edit_or_deactivate(): void
    {
        $company = Company::factory()->create();
        $contact = Contact::factory()->for($company)->create();
        $this->actingAs($this->agent());

        $this->get(route('companies.create'))->assertForbidden();
        $this->post(route('companies.store'), ['name' => 'Hack Co'])->assertForbidden();
        $this->get(route('companies.edit', $company))->assertForbidden();
        $this->put(route('companies.update', $company), ['name' => 'Renamed'])->assertForbidden();
        $this->patch(route('companies.active', $company))->assertForbidden();

        $this->get(route('contacts.create'))->assertForbidden();
        $this->post(route('contacts.store'), $this->contactPayload($company))->assertForbidden();
        $this->get(route('contacts.edit', $contact))->assertForbidden();
        $this->put(route('contacts.update', $contact), $this->contactPayload($company))->assertForbidden();
        $this->patch(route('contacts.active', $contact))->assertForbidden();

        $this->assertDatabaseMissing('companies', ['name' => 'Hack Co']);
        $this->assertDatabaseMissing('companies', ['name' => 'Renamed']);
        $this->assertTrue($company->fresh()->is_active);
        $this->assertTrue($contact->fresh()->is_active);
    }

    public function test_agents_do_not_see_admin_buttons(): void
    {
        $company = Company::factory()->create();
        $this->actingAs($this->agent());

        $this->get(route('companies.index'))->assertDontSee('+ New company');
        $this->get(route('companies.show', $company))->assertDontSee('Deactivate')->assertDontSee('Edit');
    }

    public function test_nothing_can_be_deleted(): void
    {
        $company = Company::factory()->create();
        $contact = Contact::factory()->for($company)->create();
        $this->actingAs($this->admin());

        $this->delete("/companies/{$company->company_id}")->assertStatus(405);
        $this->delete("/contacts/{$contact->contact_id}")->assertStatus(405);
    }

    // ---- companies (admin) -----------------------------------------------

    public function test_admin_creates_a_company_with_generated_code(): void
    {
        $this->actingAs($this->admin())
            ->post(route('companies.store'), ['name' => 'Fresh Client', 'city' => 'Pune'])
            ->assertRedirect();

        $company = Company::where('name', 'Fresh Client')->firstOrFail();
        $this->assertSame(sprintf('CMP%03d', $company->company_id), $company->company_code);
        $this->assertTrue($company->is_active);
    }

    public function test_company_name_must_be_unique(): void
    {
        Company::factory()->create(['name' => 'Taken Name']);

        $this->actingAs($this->admin())
            ->post(route('companies.store'), ['name' => 'Taken Name'])
            ->assertSessionHasErrors('name');
    }

    public function test_admin_edits_a_company_without_changing_its_code(): void
    {
        $company = Company::factory()->create(['company_code' => 'CMP777']);

        $this->actingAs($this->admin())
            ->put(route('companies.update', $company), ['name' => 'Renamed Co', 'company_code' => 'HACKED'])
            ->assertRedirect(route('companies.show', $company));

        $company->refresh();
        $this->assertSame('Renamed Co', $company->name);
        $this->assertSame('CMP777', $company->company_code);
    }

    public function test_admin_can_deactivate_and_reactivate_a_company(): void
    {
        $company = Company::factory()->create();
        $this->actingAs($this->admin());

        $this->patch(route('companies.active', $company));
        $this->assertFalse($company->fresh()->is_active);

        $this->patch(route('companies.active', $company));
        $this->assertTrue($company->fresh()->is_active);
    }

    // ---- contacts (admin) ------------------------------------------------

    public function test_admin_creates_a_contact(): void
    {
        $company = Company::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('contacts.store'), $this->contactPayload($company))
            ->assertRedirect();

        $this->assertDatabaseHas('contacts', [
            'email' => 'test.person@example.com',
            'company_id' => $company->company_id,
            'is_active' => 1,
        ]);
    }

    public function test_contact_requires_valid_company_and_unique_email(): void
    {
        $company = Company::factory()->create();
        Contact::factory()->for($company)->create(['email' => 'dup@example.com']);
        $this->actingAs($this->admin());

        $this->post(route('contacts.store'), $this->contactPayload($company, ['email' => 'dup@example.com']))
            ->assertSessionHasErrors('email');
        $this->post(route('contacts.store'), $this->contactPayload($company, ['company_id' => 99999]))
            ->assertSessionHasErrors('company_id');
        $this->post(route('contacts.store'), $this->contactPayload($company, ['product_role' => 'boss']))
            ->assertSessionHasErrors('product_role');
    }

    public function test_contact_can_keep_its_own_email_when_edited(): void
    {
        $company = Company::factory()->create();
        $contact = Contact::factory()->for($company)->create(['email' => 'same@example.com']);

        $this->actingAs($this->admin())
            ->put(route('contacts.update', $contact), $this->contactPayload($company, [
                'email' => 'same@example.com', 'full_name' => 'Edited Name',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Edited Name', $contact->fresh()->full_name);
    }

    public function test_only_one_primary_contact_per_company(): void
    {
        $company = Company::factory()->create();
        $first = Contact::factory()->for($company)->create(['is_primary' => true]);
        $this->actingAs($this->admin());

        $this->post(route('contacts.store'), $this->contactPayload($company, ['is_primary' => '1']));

        $second = Contact::where('email', 'test.person@example.com')->firstOrFail();
        $this->assertTrue($second->is_primary);
        $this->assertFalse($first->fresh()->is_primary);
    }

    public function test_admin_can_deactivate_a_contact_and_it_is_hidden_by_default(): void
    {
        $contact = Contact::factory()->create(['full_name' => 'Soon Hidden']);
        $this->actingAs($this->admin());

        $this->patch(route('contacts.active', $contact));
        $this->assertFalse($contact->fresh()->is_active);

        $this->get(route('contacts.index'))->assertDontSee('Soon Hidden');
        $this->get(route('contacts.index', ['status' => 'inactive']))->assertSee('Soon Hidden');
        $this->get(route('contacts.index', ['status' => 'all']))->assertSee('Soon Hidden');
    }

    // ---- search ----------------------------------------------------------

    public function test_contacts_are_searchable_by_name_email_phone_and_company(): void
    {
        $acme = Company::factory()->create(['name' => 'Zebra Traders']);
        Contact::factory()->for($acme)->create([
            'full_name' => 'Quentin Findme', 'email' => 'quentin@findme.example', 'phone' => '9876501234',
        ]);
        Contact::factory()->create(['full_name' => 'Other Person']);
        $this->actingAs($this->agent());

        foreach (['Quentin', 'findme.example', '98765012', 'Zebra'] as $term) {
            $this->get(route('contacts.index', ['q' => $term]))
                ->assertOk()->assertSee('Quentin Findme')->assertDontSee('Other Person');
        }
    }

    public function test_contacts_can_be_filtered_by_company(): void
    {
        $a = Company::factory()->create();
        $b = Company::factory()->create();
        Contact::factory()->for($a)->create(['full_name' => 'In Company A']);
        Contact::factory()->for($b)->create(['full_name' => 'In Company B']);
        $this->actingAs($this->agent());

        $this->get(route('contacts.index', ['company_id' => $a->company_id]))
            ->assertSee('In Company A')->assertDontSee('In Company B');
    }

    public function test_companies_are_searchable_and_show_contact_counts(): void
    {
        $match = Company::factory()->create(['name' => 'Searchable Ltd', 'city' => 'Rajkot']);
        Contact::factory()->count(3)->for($match)->create();
        Company::factory()->create(['name' => 'Different Inc']);
        $this->actingAs($this->agent());

        $this->get(route('companies.index', ['q' => 'Rajkot']))
            ->assertOk()->assertSee('Searchable Ltd')->assertDontSee('Different Inc');
    }

    public function test_search_treats_percent_signs_literally(): void
    {
        Contact::factory()->create(['full_name' => 'Plain Person']);
        $this->actingAs($this->agent());

        $this->get(route('contacts.index', ['q' => '%']))->assertDontSee('Plain Person');
    }
}
