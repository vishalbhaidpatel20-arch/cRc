<?php

namespace Tests\Unit\Models;

use App\Models\Company;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    private function contact(array $attrs = []): Contact
    {
        return Contact::factory()->create(array_merge([
            'full_name' => 'Plain Person',
            'email' => 'plain@example.com',
            'phone' => '1111111111',
            'whatsapp_no' => '2222222222',
            'position' => 'Clerk',
            'business_name' => 'Plain Biz',
        ], $attrs));
    }

    private function decoy(): Contact
    {
        return $this->contact(['email' => 'decoy@example.com', 'full_name' => 'Decoy Person']);
    }

    private function ids(?string $term): array
    {
        return Contact::search($term)->pluck('contacts.contact_id')->all();
    }

    /** @return list<string> */
    private function enumValues(string $column): array
    {
        $type = DB::selectOne(
            'SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [DB::getDatabaseName(), 'contacts', $column]
        )->t;
        preg_match_all("/'([^']*)'/", $type, $m);

        return $m[1];
    }

    public function test_search_matches_full_name(): void
    {
        $hit = $this->contact(['full_name' => 'Quentin Findme']);
        $this->decoy();

        $this->assertSame([$hit->contact_id], $this->ids('Quentin'));
    }

    public function test_search_matches_email(): void
    {
        $hit = $this->contact(['email' => 'q@findme.example']);
        $this->decoy();

        $this->assertSame([$hit->contact_id], $this->ids('findme.example'));
    }

    public function test_search_matches_phone(): void
    {
        $hit = $this->contact(['phone' => '9876501234']);
        $this->decoy();

        $this->assertSame([$hit->contact_id], $this->ids('98765012'));
    }

    public function test_search_matches_whatsapp_number(): void
    {
        $hit = $this->contact(['whatsapp_no' => '9555500000']);
        $this->decoy();

        $this->assertSame([$hit->contact_id], $this->ids('95555000'));
    }

    public function test_search_matches_position(): void
    {
        $hit = $this->contact(['position' => 'Chief Wizard']);
        $this->decoy();

        $this->assertSame([$hit->contact_id], $this->ids('Wizard'));
    }

    public function test_search_matches_business_name(): void
    {
        $hit = $this->contact(['business_name' => 'Gadget Emporium']);
        $this->decoy();

        $this->assertSame([$hit->contact_id], $this->ids('Emporium'));
    }

    public function test_search_matches_company_name(): void
    {
        $company = Company::factory()->create(['name' => 'Zebra Traders']);
        $hit = $this->contact(['company_id' => $company->company_id]);
        $this->decoy();

        $this->assertSame([$hit->contact_id], $this->ids('Zebra'));
    }

    public function test_search_is_case_insensitive_and_trimmed(): void
    {
        $hit = $this->contact(['full_name' => 'Quentin Findme']);

        $this->assertSame([$hit->contact_id], $this->ids('  qUENTIN '));
    }

    public function test_blank_and_null_terms_return_everything(): void
    {
        $this->contact();
        $this->decoy();

        $this->assertCount(2, $this->ids(''));
        $this->assertCount(2, $this->ids('  '));
        $this->assertCount(2, $this->ids(null));
    }

    public function test_percent_is_treated_literally(): void
    {
        $this->contact();
        $hit = $this->contact(['email' => 'o@example.com', 'position' => '100% Owner']);

        $this->assertSame([$hit->contact_id], $this->ids('%'));
    }

    public function test_underscore_is_treated_literally(): void
    {
        $this->contact(['full_name' => 'Abc Person']);
        $hit = $this->contact(['email' => 'o@example.com', 'full_name' => 'A_c Person']);

        $this->assertSame([$hit->contact_id], $this->ids('A_c'));
    }

    public function test_company_relation(): void
    {
        $company = Company::factory()->create();
        $contact = $this->contact(['company_id' => $company->company_id]);

        $this->assertTrue($contact->company->is($company));
    }

    public function test_product_roles_match_database_enum(): void
    {
        $this->assertSame($this->enumValues('product_role'), Contact::PRODUCT_ROLES);
    }

    public function test_channels_match_database_enum(): void
    {
        $this->assertSame($this->enumValues('preferred_channel'), Contact::CHANNELS);
    }

    public function test_flags_are_cast_to_booleans(): void
    {
        $contact = $this->contact(['is_primary' => 1, 'is_active' => 0])->fresh();

        $this->assertTrue($contact->is_primary);
        $this->assertFalse($contact->is_active);
    }

    public function test_last_contacted_at_is_carbon_or_null(): void
    {
        $contact = $this->contact();
        $this->assertNull($contact->fresh()->last_contacted_at);

        $contact->forceFill(['last_contacted_at' => '2026-01-02 03:04:05'])->save();

        $this->assertInstanceOf(Carbon::class, $contact->fresh()->last_contacted_at);
        $this->assertSame('2026-01-02 03:04:05', $contact->fresh()->last_contacted_at->format('Y-m-d H:i:s'));
    }

    public function test_created_at_is_cast_to_carbon(): void
    {
        $this->assertInstanceOf(Carbon::class, $this->contact()->fresh()->created_at);
    }
}
