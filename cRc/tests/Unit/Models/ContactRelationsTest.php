<?php

namespace Tests\Unit\Models;

use App\Models\Contact;
use App\Models\ProductModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Contact::modules() and Contact::initials(), which ContactTest does not cover. */
class ContactRelationsTest extends TestCase
{
    use RefreshDatabase;

    private function initials(string $name): string
    {
        return (new Contact(['full_name' => $name]))->initials();
    }

    public function test_modules_relation_returns_attached_modules(): void
    {
        $contact = Contact::factory()->create();
        $m = ProductModule::create(['name' => 'Billing']);
        $contact->modules()->attach($m->module_id);

        $this->assertSame([$m->module_id], $contact->modules->pluck('module_id')->all());
    }

    public function test_modules_are_ordered_by_name(): void
    {
        $contact = Contact::factory()->create();
        $z = ProductModule::create(['name' => 'Reports']);
        $a = ProductModule::create(['name' => 'Billing']);
        $m = ProductModule::create(['name' => 'Invoicing']);
        $contact->modules()->attach([$z->module_id, $a->module_id, $m->module_id]);

        $this->assertSame(['Billing', 'Invoicing', 'Reports'], $contact->modules->pluck('name')->all());
    }

    public function test_modules_are_empty_by_default_and_per_contact(): void
    {
        $withModule = Contact::factory()->create();
        $without = Contact::factory()->create();
        $withModule->modules()->attach(ProductModule::create(['name' => 'Billing'])->module_id);

        $this->assertCount(0, $without->modules);
        $this->assertCount(1, $withModule->modules);
    }

    public function test_initials_two_names(): void
    {
        $this->assertSame('RS', $this->initials('Rohan Shah'));
    }

    public function test_initials_single_name(): void
    {
        $this->assertSame('R', $this->initials('Rohan'));
    }

    public function test_initials_uses_only_first_two_words(): void
    {
        $this->assertSame('AB', $this->initials('Anna Beth Carter'));
    }

    public function test_initials_ignores_extra_spaces(): void
    {
        $this->assertSame('RS', $this->initials("   rohan   \t  shah  "));
    }

    public function test_initials_are_uppercased(): void
    {
        $this->assertSame('RS', $this->initials('rohan shah'));
    }

    public function test_initials_handle_unicode(): void
    {
        $this->assertSame('ÜÇ', $this->initials('Ünal Çelik'));
    }

    public function test_initials_lowercase_unicode_is_uppercased(): void
    {
        $this->assertSame('ÜÇ', $this->initials('ünal çelik'));
    }

    public function test_initials_empty_string_gives_question_mark(): void
    {
        $this->assertSame('?', $this->initials(''));
    }

    public function test_initials_whitespace_only_gives_question_mark(): void
    {
        $this->assertSame('?', $this->initials('    '));
    }

    public function test_initials_very_long_name_still_two_letters(): void
    {
        $this->assertSame('AB', $this->initials(str_repeat('A', 5000).' '.str_repeat('B', 5000)));
    }
}
