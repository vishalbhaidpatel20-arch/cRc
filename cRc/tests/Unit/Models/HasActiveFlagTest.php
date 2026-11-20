<?php

namespace Tests\Unit\Models;

use App\Models\Company;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Contact;
use App\Models\MessageTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The HasActiveFlag trait: one block of code shared by four models. */
class HasActiveFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_four_models_with_is_active_use_the_trait(): void
    {
        foreach ([Company::class, Contact::class, MessageTemplate::class, User::class] as $model) {
            $this->assertContains(HasActiveFlag::class, class_uses_recursive($model), "{$model} should use HasActiveFlag");
        }
    }

    public function test_active_and_inactive_scopes_filter_rows(): void
    {
        $on = Company::factory()->create(['name' => 'On Co', 'is_active' => true]);
        $off = Company::factory()->create(['name' => 'Off Co', 'is_active' => false]);

        $this->assertSame([$on->company_id], Company::active()->pluck('company_id')->all());
        $this->assertSame([$off->company_id], Company::inactive()->pluck('company_id')->all());
        $this->assertSame(2, Company::count());   // without a scope, both
    }

    public function test_the_same_scope_works_on_another_model_and_after_other_conditions(): void
    {
        User::factory()->agent()->create(['is_active' => true]);
        User::factory()->agent()->create(['is_active' => false]);
        User::factory()->admin()->create(['is_active' => true]);

        $this->assertSame(1, User::where('role', User::ROLE_AGENT)->active()->count());
        $this->assertSame(2, User::active()->count());
    }

    public function test_the_scope_names_the_table_so_it_works_in_a_join(): void
    {
        $sql = Contact::query()->join('companies', 'companies.company_id', '=', 'contacts.company_id')->active()->toSql();

        $this->assertStringContainsString('`contacts`.`is_active`', $sql);   // not an ambiguous "is_active"
    }

    public function test_is_active_and_toggle_active(): void
    {
        $template = MessageTemplate::factory()->create(['is_active' => true]);
        $this->assertTrue($template->isActive());

        $this->assertFalse($template->toggleActive());   // returns the new state
        $this->assertFalse($template->fresh()->isActive());   // and it was saved

        $this->assertTrue($template->toggleActive());
        $this->assertTrue($template->fresh()->isActive());
    }
}
