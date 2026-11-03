<?php

namespace Tests\Unit\Models;

use App\Models\Contact;
use App\Models\ProductModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_key_and_timestamps_configuration(): void
    {
        $model = new ProductModule;

        $this->assertSame('product_modules', $model->getTable());
        $this->assertSame('module_id', $model->getKeyName());
        $this->assertTrue($model->getIncrementing());
        $this->assertFalse($model->usesTimestamps());
    }

    public function test_fillable_attributes(): void
    {
        $this->assertSame(['name', 'description'], (new ProductModule)->getFillable());
    }

    public function test_module_id_is_not_mass_assignable(): void
    {
        $module = new ProductModule(['module_id' => 999, 'name' => 'Billing']);

        $this->assertNull($module->module_id);
        $this->assertSame('Billing', $module->name);
    }

    public function test_description_is_optional(): void
    {
        $module = ProductModule::create(['name' => 'Billing'])->fresh();

        $this->assertNull($module->description);
    }

    public function test_contacts_relation_returns_attached_contacts(): void
    {
        $module = ProductModule::create(['name' => 'Billing']);
        $a = Contact::factory()->create();
        $b = Contact::factory()->create();
        Contact::factory()->create();
        $module->contacts()->attach([$a->contact_id, $b->contact_id]);

        $this->assertEqualsCanonicalizing(
            [$a->contact_id, $b->contact_id],
            $module->contacts->pluck('contact_id')->all()
        );
    }
}
