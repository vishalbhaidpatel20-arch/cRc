<?php

namespace Tests\Unit\Models;

use App\Models\Contact;
use App\Models\ContactProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ContactProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_key_and_timestamps_configuration(): void
    {
        $model = new ContactProfile;

        $this->assertSame('contact_profiles', $model->getTable());
        $this->assertSame('contact_id', $model->getKeyName());
        $this->assertFalse($model->getIncrementing());
        $this->assertFalse($model->usesTimestamps());
    }

    public function test_fillable_attributes(): void
    {
        $this->assertSame(
            ['contact_id', 'alt_phone', 'birthday', 'language', 'preferred_time', 'notes'],
            (new ContactProfile)->getFillable()
        );
    }

    public function test_contact_id_is_kept_as_the_primary_key_when_created(): void
    {
        $contact = Contact::factory()->create();
        $profile = ContactProfile::create(['contact_id' => $contact->contact_id, 'language' => 'Gujarati']);

        $this->assertSame($contact->contact_id, $profile->getKey());
        $this->assertSame('Gujarati', ContactProfile::find($contact->contact_id)->language);
    }

    public function test_birthday_is_cast_to_date(): void
    {
        $contact = Contact::factory()->create();
        ContactProfile::create(['contact_id' => $contact->contact_id, 'birthday' => '1990-04-17']);

        $profile = ContactProfile::find($contact->contact_id);

        $this->assertInstanceOf(Carbon::class, $profile->birthday);
        $this->assertSame('1990-04-17', $profile->birthday->format('Y-m-d'));
    }

    public function test_optional_fields_default_to_null(): void
    {
        $contact = Contact::factory()->create();
        ContactProfile::create(['contact_id' => $contact->contact_id]);

        $profile = ContactProfile::find($contact->contact_id);

        $this->assertNull($profile->alt_phone);
        $this->assertNull($profile->birthday);
        $this->assertNull($profile->language);
        $this->assertNull($profile->preferred_time);
        $this->assertNull($profile->notes);
    }

    public function test_contact_relation_returns_owner(): void
    {
        $contact = Contact::factory()->create();
        $profile = ContactProfile::create(['contact_id' => $contact->contact_id]);

        $this->assertTrue($profile->contact->is($contact));
    }

    public function test_contact_profile_relation_returns_profile(): void
    {
        $contact = Contact::factory()->create();
        ContactProfile::create(['contact_id' => $contact->contact_id, 'notes' => 'Prefers mornings']);

        $this->assertSame('Prefers mornings', $contact->profile->notes);
    }

    public function test_contact_without_profile_returns_null(): void
    {
        $this->assertNull(Contact::factory()->create()->profile);
    }
}
