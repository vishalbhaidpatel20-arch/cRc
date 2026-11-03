<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Creating a company from the pop-up on the companies list, and landing back on that list. */
class CompanyModalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_the_list_page_has_the_popup_with_all_fields_for_admins(): void
    {
        $this->actingAs($this->admin)->get(route('companies.index'))->assertOk()
            ->assertSee('<dialog id="company-modal"', false)
            ->assertSee('id="new-company-link"', false)
            ->assertSee('action="'.route('companies.store').'"', false)
            ->assertSee('name="_modal" value="company"', false)
            ->assertSee('name="name"', false)->assertSee('name="industry"', false)
            ->assertSee('name="email"', false)->assertSee('name="phone"', false)->assertSee('name="city"', false)
            ->assertSee('Create company')
            ->assertSee('is generated automatically');
    }

    public function test_the_new_company_link_still_points_at_the_stand_alone_page_without_javascript(): void
    {
        $this->actingAs($this->admin)->get(route('companies.index'))
            ->assertSee('href="'.route('companies.create').'"', false);

        $this->get(route('companies.create'))->assertOk()->assertSee('New company')->assertSee('name="industry"', false);
    }

    public function test_agents_get_neither_the_button_nor_the_popup(): void
    {
        $this->actingAs(User::factory()->agent()->create())->get(route('companies.index'))->assertOk()
            ->assertDontSee('company-modal')->assertDontSee('+ New company')->assertDontSee('new-company-link');
    }

    public function test_creating_from_the_popup_lands_on_the_list_with_the_company_highlighted(): void
    {
        $response = $this->actingAs($this->admin)->post(route('companies.store'), [
            'name' => 'Popup Traders', 'industry' => 'Retail', 'city' => 'Pune', '_modal' => 'company',
        ]);

        $company = Company::where('name', 'Popup Traders')->firstOrFail();
        $response->assertRedirect(route('companies.index'))
            ->assertSessionHas('highlight', $company->company_id)
            ->assertSessionHas('status', "Company Popup Traders created ({$company->company_code}).");

        $this->get(route('companies.index'))->assertOk()
            ->assertSee('Popup Traders')->assertSee($company->company_code)->assertSee('Retail')->assertSee('Pune')
            ->assertSee('class="row-new"', false)->assertSee('>New<', false)
            ->assertSee("Company Popup Traders created ({$company->company_code}).");
    }

    public function test_the_highlight_goes_away_on_the_next_visit(): void
    {
        $this->actingAs($this->admin)->post(route('companies.store'), ['name' => 'One Shot Ltd']);

        $this->get(route('companies.index'))->assertSee('class="row-new"', false);
        $this->get(route('companies.index'))->assertDontSee('class="row-new"', false)->assertDontSee('created (')->assertSee('One Shot Ltd');
    }

    public function test_a_company_that_sorts_onto_a_later_page_is_shown_on_that_page(): void
    {
        foreach (range(1, 25) as $i) {
            Company::factory()->create(['name' => sprintf('Alpha %02d', $i)]);
        }

        $response = $this->actingAs($this->admin)->post(route('companies.store'), ['name' => 'Zulu Last Company']);

        $response->assertRedirect(route('companies.index', ['page' => 2]));
        $this->get(route('companies.index', ['page' => 2]))->assertSee('Zulu Last Company')->assertSee('class="row-new"', false);
    }

    public function test_a_company_that_sorts_first_stays_on_page_one(): void
    {
        foreach (range(1, 25) as $i) {
            Company::factory()->create(['name' => sprintf('Mid %02d', $i)]);
        }

        $this->actingAs($this->admin)->post(route('companies.store'), ['name' => 'AAA First Company'])
            ->assertRedirect(route('companies.index'));
        $this->get(route('companies.index'))->assertSee('AAA First Company');
    }

    public function test_page_boundary_is_exact(): void
    {
        // 19 companies before the new one: it is the 20th row, still page 1. With 20 before it, page 2.
        foreach (range(1, 19) as $i) {
            Company::factory()->create(['name' => sprintf('Before %02d', $i)]);
        }
        $this->actingAs($this->admin)->post(route('companies.store'), ['name' => 'Zzz Twentieth'])->assertRedirect(route('companies.index'));

        $this->actingAs($this->admin)->post(route('companies.store'), ['name' => 'Zzz Twenty First'])
            ->assertRedirect(route('companies.index', ['page' => 2]));
    }

    public function test_inactive_companies_do_not_push_the_new_one_onto_a_later_page(): void
    {
        foreach (range(1, 30) as $i) {
            Company::factory()->inactive()->create(['name' => sprintf('Dormant %02d', $i)]);
        }

        $this->actingAs($this->admin)->post(route('companies.store'), ['name' => 'Zzz Fresh'])->assertRedirect(route('companies.index'));
    }

    public function test_a_failed_submit_returns_to_the_list_and_reopens_the_popup_with_errors_and_input(): void
    {
        Company::factory()->create(['name' => 'Taken Name']);

        $this->actingAs($this->admin)->from(route('companies.index'))
            ->post(route('companies.store'), ['name' => 'Taken Name', 'city' => 'Rajkot', 'email' => 'not-an-email', '_modal' => 'company'])
            ->assertRedirect(route('companies.index'));

        $html = $this->get(route('companies.index'))->assertOk()->getContent();

        $this->assertStringContainsString('has already been taken', $html);
        $this->assertStringContainsString('value="Taken Name"', $html);
        $this->assertStringContainsString('value="Rajkot"', $html);
        // The script that re-opens the dialog is only emitted for a failed pop-up submit.
        $this->assertMatchesRegularExpression('/open\(\);\s*\/\/ the pop-up was submitted with mistakes/', $html);
        $this->assertDatabaseCount('companies', 1);
    }

    public function test_the_popup_is_not_reopened_on_a_normal_visit_or_for_other_forms(): void
    {
        $html = $this->actingAs($this->admin)->get(route('companies.index'))->getContent();
        $this->assertDoesNotMatchRegularExpression('/open\(\);\s*\/\/ the pop-up was submitted/', $html);

        // Errors from a different form (no _modal marker) must not pop the dialog open.
        $this->from(route('companies.index'))->post(route('companies.store'), ['name' => '']);
        $this->assertDoesNotMatchRegularExpression('/open\(\);\s*\/\/ the pop-up was submitted/', $this->get(route('companies.index'))->getContent());
    }

    public function test_validation_rules_still_apply_to_the_popup(): void
    {
        $this->actingAs($this->admin);

        foreach ([
            ['name' => ''], ['name' => str_repeat('x', 151)], ['name' => 'Ok', 'email' => 'nope'],
            ['name' => 'Ok', 'phone' => str_repeat('1', 31)], ['name' => 'Ok', 'city' => str_repeat('c', 81)],
            ['name' => 'Ok', 'industry' => str_repeat('i', 101)],
        ] as $bad) {
            $this->post(route('companies.store'), $bad + ['_modal' => 'company'])->assertSessionHasErrors(array_key_first($bad) === 'name' && ($bad['name'] ?? 'x') !== 'Ok' ? 'name' : array_key_last($bad));
        }

        $this->assertDatabaseCount('companies', 0);
    }

    public function test_agents_cannot_post_to_create_a_company_even_with_the_popup_marker(): void
    {
        $this->actingAs(User::factory()->agent()->create())
            ->post(route('companies.store'), ['name' => 'Sneaky Co', '_modal' => 'company'])->assertForbidden();

        $this->assertDatabaseMissing('companies', ['name' => 'Sneaky Co']);
    }

    public function test_the_code_is_generated_and_the_marker_is_not_saved(): void
    {
        $this->actingAs($this->admin)->post(route('companies.store'), ['name' => 'Coded Co', '_modal' => 'company', 'company_code' => 'HACK', 'is_active' => 0]);

        $company = Company::where('name', 'Coded Co')->firstOrFail();
        $this->assertSame(sprintf('CMP%03d', $company->company_id), $company->company_code);
        $this->assertTrue($company->is_active);
    }

    public function test_the_stand_alone_form_still_works_and_also_returns_to_the_list(): void
    {
        $this->actingAs($this->admin)->post(route('companies.store'), ['name' => 'Fallback Co'])
            ->assertRedirect(route('companies.index'));
        $this->get(route('companies.index'))->assertSee('Fallback Co');
    }

    public function test_editing_a_company_is_unchanged(): void
    {
        $company = Company::factory()->create(['name' => 'Before Edit']);

        $this->actingAs($this->admin)->put(route('companies.update', $company), ['name' => 'After Edit'])
            ->assertRedirect(route('companies.show', $company));
        $this->assertSame('After Edit', $company->fresh()->name);
    }
}
