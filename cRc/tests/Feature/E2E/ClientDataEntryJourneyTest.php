<?php

namespace Tests\Feature\E2E;

use App\Models\Company;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientDataEntryJourneyTest extends TestCase
{
    use JourneyHelpers;
    use RefreshDatabase;

    private function contactData(Company $company, array $over = []): array
    {
        return array_merge([
            'company_id' => $company->company_id,
            'full_name' => 'Placeholder',
            'email' => 'placeholder@example.com',
            'product_role' => 'end_user',
            'preferred_channel' => 'call',
        ], $over);
    }

    public function test_admin_enters_a_client_company_and_its_contacts_end_to_end(): void
    {
        $admin = $this->makeAdmin();
        Company::factory()->create(['name' => 'Bystander Ltd']);
        Contact::factory()->for(Company::where('name', 'Bystander Ltd')->first())
            ->create(['full_name' => 'Unrelated Uma', 'phone' => '1112223334']);

        $this->loginAs($admin->email)->assertSee('Admin dashboard');

        // 1. Create the company; the code is generated and shown on the redirected page.
        $this->browse(route('companies.create'))->assertOk();
        $resp = $this->submit('POST', route('companies.store'), [
            'name' => 'Globex Pvt Ltd', 'industry' => 'Manufacturing', 'city' => 'Surat',
            'company_code' => 'HACKED',
        ], route('companies.create'));
        $company = Company::where('name', 'Globex Pvt Ltd')->firstOrFail();
        $code = sprintf('CMP%03d', $company->company_id);
        $this->assertSame($code, $company->company_code);
        $resp->assertOk()->assertSee('Company created.')->assertSee($code)
            ->assertSee('Globex Pvt Ltd')->assertSee('No contacts yet.')->assertSee('+ Add contact');

        // 2. Add the first contact via the company's "Add contact" link (company preselected).
        $this->browse(route('contacts.create', ['company_id' => $company->company_id]))
            ->assertOk()->assertSee('Globex Pvt Ltd');
        $this->submit('POST', route('contacts.store'), $this->contactData($company, [
            'full_name' => 'Alice Anderson', 'email' => 'alice@globex.example',
            'phone' => '9811100001', 'position' => 'Buyer', 'is_primary' => '1',
        ]))->assertOk()->assertSee('Contact created.')->assertSee('Alice Anderson')->assertSee('Primary');
        $alice = Contact::where('email', 'alice@globex.example')->firstOrFail();
        $this->assertTrue($alice->is_primary);

        // 3. Add a second contact and mark it primary: Alice loses the flag.
        $this->submit('POST', route('contacts.store'), $this->contactData($company, [
            'full_name' => 'Bob Brown', 'email' => 'bob@globex.example',
            'phone' => '9822200002', 'position' => 'Manager', 'is_primary' => '1',
        ]))->assertOk()->assertSee('Contact created.');
        $bob = Contact::where('email', 'bob@globex.example')->firstOrFail();
        $this->assertTrue($bob->is_primary);
        $this->assertFalse($alice->fresh()->is_primary);
        $this->assertSame(1, Contact::where('company_id', $company->company_id)->where('is_primary', 1)->count());

        // Company page lists both contacts with exactly one Primary tag.
        $page = $this->browse(route('companies.show', $company))
            ->assertSee('Alice Anderson')->assertSee('Bob Brown');
        $this->assertSame(1, substr_count($page->getContent(), '<span class="tag">Primary</span>'));

        // A duplicate email is rejected and nothing is added.
        $this->submit('POST', route('contacts.store'), $this->contactData($company, [
            'full_name' => 'Dupe', 'email' => 'alice@globex.example',
        ]), route('contacts.create'))->assertOk();
        $this->assertSame(2, Contact::where('company_id', $company->company_id)->count());

        // 4. Edit Alice (keeps own email) and see the change on the show page.
        $this->browse(route('contacts.edit', $alice))->assertOk()->assertSee('Alice Anderson');
        $this->submit('PUT', route('contacts.update', $alice), $this->contactData($company, [
            'full_name' => 'Alice Archer', 'email' => 'alice@globex.example',
            'phone' => '9811100001', 'position' => 'Head of Procurement',
        ]), route('contacts.edit', $alice))
            ->assertOk()->assertSee('Contact updated.')->assertSee('Alice Archer')
            ->assertSee('Head of Procurement')->assertDontSee('Alice Anderson');

        // 5. Search by name, phone and company; unrelated contacts never show.
        $this->browse(route('contacts.index', ['q' => 'Archer']))
            ->assertSee('Alice Archer')->assertDontSee('Bob Brown')->assertDontSee('Unrelated Uma');
        $this->browse(route('contacts.index', ['q' => '98222000']))
            ->assertSee('Bob Brown')->assertDontSee('Alice Archer')->assertDontSee('Unrelated Uma');
        $this->browse(route('contacts.index', ['q' => 'Globex']))
            ->assertSee('Alice Archer')->assertSee('Bob Brown')->assertDontSee('Unrelated Uma');
        $this->browse(route('contacts.index', ['company_id' => $company->company_id]))
            ->assertSee('Alice Archer')->assertSee('Bob Brown')->assertDontSee('Unrelated Uma');
        $this->browse(route('companies.index', ['q' => 'Surat']))
            ->assertSee('Globex Pvt Ltd')->assertDontSee('Bystander Ltd');

        // 6. Deactivate Bob: hidden by default, visible under inactive/all.
        $this->submit('PATCH', route('contacts.active', $bob), [], route('contacts.show', $bob))
            ->assertOk()->assertSee('Contact deactivated.')->assertSee('Inactive')->assertSee('Activate');
        $this->assertFalse($bob->fresh()->is_active);
        $this->browse(route('contacts.index'))->assertSee('Alice Archer')->assertDontSee('Bob Brown');
        $this->browse(route('contacts.index', ['status' => 'inactive']))
            ->assertSee('Bob Brown')->assertDontSee('Alice Archer');
        $this->browse(route('contacts.index', ['status' => 'all']))
            ->assertSee('Bob Brown')->assertSee('Alice Archer');

        // 7. Deactivate the company; its record and contacts are preserved (no deletes).
        $this->submit('PATCH', route('companies.active', $company), [], route('companies.show', $company))
            ->assertOk()->assertSee('Company deactivated.')->assertSee('Inactive');
        $this->assertFalse($company->fresh()->is_active);
        $this->assertSame(2, Contact::where('company_id', $company->company_id)->count());
        $this->browse(route('companies.show', $company))->assertSee('Alice Archer');
        $this->assertSame($code, $company->fresh()->company_code);

        // The deactivated company can no longer be chosen for a new contact.
        $this->browse(route('contacts.create'))->assertOk()->assertDontSee('Globex Pvt Ltd')->assertSee('Bystander Ltd');

        // 8. Reactivate it again.
        $this->submit('PATCH', route('companies.active', $company), [], route('companies.show', $company))
            ->assertSee('Company activated.');
        $this->assertTrue($company->fresh()->is_active);

        $this->logout()->assertSee('Log in');
    }

    public function test_nothing_can_be_deleted_even_by_an_admin_in_session(): void
    {
        $admin = $this->makeAdmin();
        $company = Company::factory()->create();
        $contact = Contact::factory()->for($company)->create();
        $this->loginAs($admin->email);

        $this->freshRequest()->delete("/companies/{$company->company_id}")->assertStatus(405);
        $this->freshRequest()->delete("/contacts/{$contact->contact_id}")->assertStatus(405);
        $this->assertDatabaseHas('companies', ['company_id' => $company->company_id]);
        $this->assertDatabaseHas('contacts', ['contact_id' => $contact->contact_id]);
    }
}
