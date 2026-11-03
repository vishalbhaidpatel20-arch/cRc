<?php

namespace Tests\Unit\Database;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InteractionTriggerTest extends TestCase
{
    use RefreshDatabase;

    private function log(Contact $contact, User $user, string $at): void
    {
        DB::table('interactions')->insert([
            'contact_id' => $contact->contact_id,
            'user_id' => $user->user_id,
            'channel' => 'call',
            'direction' => 'outbound',
            'summary' => 'Trigger test',
            'interaction_at' => $at,
        ]);
    }

    private function last(Contact $contact): ?string
    {
        return DB::table('contacts')->where('contact_id', $contact->contact_id)->value('last_contacted_at');
    }

    public function test_first_interaction_sets_last_contacted_at(): void
    {
        $contact = Contact::factory()->create();
        $user = User::factory()->create();
        $this->assertNull($this->last($contact));

        $this->log($contact, $user, '2026-03-01 10:00:00');

        $this->assertSame('2026-03-01 10:00:00', $this->last($contact));
    }

    public function test_later_interaction_moves_it_forward(): void
    {
        $contact = Contact::factory()->create();
        $user = User::factory()->create();
        $this->log($contact, $user, '2026-03-01 10:00:00');

        $this->log($contact, $user, '2026-03-05 09:30:00');

        $this->assertSame('2026-03-05 09:30:00', $this->last($contact));
    }

    public function test_back_dated_interaction_does_not_move_it_backwards(): void
    {
        $contact = Contact::factory()->create();
        $user = User::factory()->create();
        $this->log($contact, $user, '2026-03-05 09:30:00');

        $this->log($contact, $user, '2026-02-01 08:00:00');

        $this->assertSame('2026-03-05 09:30:00', $this->last($contact));
    }

    public function test_other_contacts_are_unaffected(): void
    {
        $contact = Contact::factory()->create();
        $other = Contact::factory()->create();
        $user = User::factory()->create();

        $this->log($contact, $user, '2026-03-01 10:00:00');

        $this->assertNull($this->last($other));
    }
}
