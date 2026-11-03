<?php

namespace Tests\Unit\Models;

use App\Models\Company;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    private function company(array $attrs = []): Company
    {
        return Company::factory()->create(array_merge([
            'name' => 'Plain Name', 'company_code' => 'CMP001', 'city' => 'Plainville', 'industry' => 'Plainwork',
        ], $attrs));
    }

    private function decoy(): Company
    {
        return $this->company(['name' => 'Decoy', 'company_code' => 'CMP002']);
    }

    public function test_search_matches_name(): void
    {
        $hit = $this->company(['name' => 'Zebra Traders']);
        $this->decoy();

        $this->assertSame([$hit->company_id], Company::search('Zebra')->pluck('company_id')->all());
    }

    public function test_search_matches_code(): void
    {
        $hit = $this->company(['company_code' => 'XYZ123']);
        $this->decoy();

        $this->assertSame([$hit->company_id], Company::search('XYZ123')->pluck('company_id')->all());
    }

    public function test_search_matches_city(): void
    {
        $hit = $this->company(['city' => 'Rajkot']);
        $this->decoy();

        $this->assertSame([$hit->company_id], Company::search('Rajkot')->pluck('company_id')->all());
    }

    public function test_search_matches_industry(): void
    {
        $hit = $this->company(['industry' => 'Textiles']);
        $this->decoy();

        $this->assertSame([$hit->company_id], Company::search('Textiles')->pluck('company_id')->all());
    }

    public function test_search_is_case_insensitive(): void
    {
        $this->company(['name' => 'Zebra Traders']);

        $this->assertCount(1, Company::search('zEBRA')->get());
    }

    public function test_search_trims_the_term(): void
    {
        $this->company(['name' => 'Zebra Traders']);

        $this->assertCount(1, Company::search('  Zebra  ')->get());
    }

    public function test_blank_term_returns_everything(): void
    {
        $this->company();
        $this->decoy();

        $this->assertCount(2, Company::search('')->get());
        $this->assertCount(2, Company::search('   ')->get());
    }

    public function test_null_term_returns_everything(): void
    {
        $this->company();
        $this->decoy();

        $this->assertCount(2, Company::search(null)->get());
    }

    public function test_percent_is_treated_literally(): void
    {
        $this->company();
        $hit = $this->company(['name' => '50% Off Ltd', 'company_code' => 'CMP002']);

        $this->assertSame([$hit->company_id], Company::search('%')->pluck('company_id')->all());
    }

    public function test_underscore_is_treated_literally(): void
    {
        $this->company(['name' => 'Abc Corp']);
        $hit = $this->company(['name' => 'A_c Corp', 'company_code' => 'CMP002']);

        $this->assertSame([$hit->company_id], Company::search('A_c')->pluck('company_id')->all());
    }

    public function test_search_without_match_returns_nothing(): void
    {
        $this->company();

        $this->assertCount(0, Company::search('nothing-like-this')->get());
    }

    public function test_contacts_relation_returns_only_own_contacts(): void
    {
        $company = $this->company();
        $other = $this->decoy();
        Contact::factory()->count(2)->create(['company_id' => $company->company_id]);
        Contact::factory()->create(['company_id' => $other->company_id]);

        $this->assertCount(2, $company->contacts);
        $this->assertContainsOnlyInstancesOf(Contact::class, $company->contacts);
    }

    public function test_is_active_is_cast_to_boolean(): void
    {
        $this->assertFalse(Company::factory()->create(['is_active' => 0])->fresh()->is_active);
        $this->assertTrue(Company::factory()->create()->fresh()->is_active);
    }

    public function test_created_at_is_cast_to_carbon(): void
    {
        $this->assertInstanceOf(Carbon::class, Company::factory()->create()->fresh()->created_at);
    }
}
