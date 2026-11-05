<?php

namespace App\Support;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * One place that says which fields a module's form has, in which order and with which label.
 * `partials/_form-fields.blade.php` loops over this list, so a field is added, renamed or
 * reordered here and every pop-up and stand-alone page for that module follows.
 *
 * Field keys: name, label, type (text|email|select|checkboxes|checkbox), required, maxlength,
 * placeholder, value (already resolved from the model), options (value => label, select and
 * checkboxes), empty (first "choose" option of a select), error (key to read errors from when
 * it differs from the field name).
 */
class ModuleForm
{
    public const MODULES = ['company', 'contact'];

    /**
     * @param  array  $data  extra lists the form needs: contact => companies (collection), modules (collection)
     * @return list<array<string, mixed>>
     */
    public static function fields(string $module, Model $model, array $data = []): array
    {
        return match ($module) {
            'company' => self::company($model),
            'contact' => self::contact($model, $data),
            default => throw new InvalidArgumentException("Unknown form module [$module]."),
        };
    }

    private static function company(Model $company): array
    {
        return [
            self::text('name', 'Company name', $company, required: true, maxlength: 150),
            self::text('industry', 'Industry', $company, maxlength: 100),
            self::text('email', 'Email', $company, type: 'email', maxlength: 190),
            self::text('phone', 'Phone', $company, maxlength: 30),
            self::text('city', 'City', $company, maxlength: 80),
        ];
    }

    private static function contact(Model $contact, array $data): array
    {
        $companies = $data['companies'] ?? collect();
        $modules = $data['modules'] ?? collect();

        return [
            [
                'name' => 'company_id', 'label' => 'Company', 'type' => 'select', 'required' => true,
                'value' => $contact->company_id, 'empty' => 'Select a company',
                'options' => $companies->pluck('name', 'company_id')->all(),
            ],
            self::text('full_name', 'Full name', $contact, required: true),
            self::text('email', 'Email', $contact, type: 'email', required: true),
            self::text('phone', 'Phone', $contact),
            self::text('whatsapp_no', 'WhatsApp number', $contact),
            self::text('position', 'Position', $contact),
            self::text('department', 'Department', $contact),
            self::text('business_name', 'Business name', $contact),
            [
                'name' => 'product_role', 'label' => 'Role in the product', 'type' => 'select',
                'value' => $contact->product_role ?? 'end_user',
                'options' => collect(Contact::PRODUCT_ROLES)->mapWithKeys(fn ($r) => [$r => str_replace('_', ' ', $r)])->all(),
            ],
            [
                'name' => 'preferred_channel', 'label' => 'Preferred channel', 'type' => 'select',
                'value' => $contact->preferred_channel ?? 'email',
                'options' => collect(Contact::CHANNELS)->mapWithKeys(fn ($ch) => [$ch => $ch])->all(),
            ],
            [
                'name' => 'preferred_time', 'label' => 'Best time to reach', 'type' => 'text',
                'value' => $contact->profile?->preferred_time, 'placeholder' => 'e.g. 10am to 1pm',
            ],
            [
                'name' => 'modules', 'label' => 'Uses these modules', 'type' => 'checkboxes', 'error' => 'modules.*',
                'value' => $contact->exists ? $contact->modules->pluck('module_id')->all() : [],
                'options' => $modules->pluck('name', 'module_id')->all(),
                'none' => 'No modules yet. An admin can add them under Modules.',
            ],
            [
                'name' => 'is_primary', 'label' => 'Primary contact for this company', 'type' => 'checkbox',
                'value' => $contact->is_primary,
            ],
        ];
    }

    private static function text(string $name, string $label, Model $model, string $type = 'text', bool $required = false, ?int $maxlength = null): array
    {
        return [
            'name' => $name, 'label' => $label, 'type' => $type, 'required' => $required,
            'maxlength' => $maxlength, 'value' => $model->getAttribute($name),
        ];
    }
}
