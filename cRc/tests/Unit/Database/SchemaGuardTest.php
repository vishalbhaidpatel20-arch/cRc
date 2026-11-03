<?php

namespace Tests\Unit\Database;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SchemaGuardTest extends TestCase
{
    use RefreshDatabase;

    private function deleteRule(string $table, string $column): ?string
    {
        $row = DB::selectOne(
            'SELECT rc.DELETE_RULE AS r
               FROM information_schema.KEY_COLUMN_USAGE k
               JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
                 ON rc.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND rc.CONSTRAINT_NAME = k.CONSTRAINT_NAME
              WHERE k.TABLE_SCHEMA = ? AND k.TABLE_NAME = ? AND k.COLUMN_NAME = ?
                AND k.REFERENCED_TABLE_NAME IS NOT NULL',
            [DB::getDatabaseName(), $table, $column]
        );

        return $row?->r;
    }

    private function isUnique(string $table, string $column): bool
    {
        return DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND NON_UNIQUE = 0 AND SEQ_IN_INDEX = 1',
            [DB::getDatabaseName(), $table, $column]
        )->c > 0;
    }

    public function test_contacts_company_is_restrict(): void
    {
        $this->assertSame('RESTRICT', $this->deleteRule('contacts', 'company_id'));
    }

    public function test_tickets_company_is_restrict(): void
    {
        $this->assertSame('RESTRICT', $this->deleteRule('tickets', 'company_id'));
    }

    public function test_tickets_contact_is_restrict(): void
    {
        $this->assertSame('RESTRICT', $this->deleteRule('tickets', 'contact_id'));
    }

    public function test_tickets_assigned_to_is_set_null(): void
    {
        $this->assertSame('SET NULL', $this->deleteRule('tickets', 'assigned_to'));
    }

    public function test_contact_profiles_contact_is_cascade(): void
    {
        $this->assertSame('CASCADE', $this->deleteRule('contact_profiles', 'contact_id'));
    }

    public function test_contact_modules_contact_is_cascade(): void
    {
        $this->assertSame('CASCADE', $this->deleteRule('contact_modules', 'contact_id'));
    }

    public function test_user_companies_are_cascade(): void
    {
        $this->assertSame('CASCADE', $this->deleteRule('user_companies', 'user_id'));
        $this->assertSame('CASCADE', $this->deleteRule('user_companies', 'company_id'));
    }

    public function test_ticket_comments_ticket_is_cascade(): void
    {
        $this->assertSame('CASCADE', $this->deleteRule('ticket_comments', 'ticket_id'));
    }

    public function test_unique_indexes_exist(): void
    {
        $this->assertTrue($this->isUnique('users', 'email'));
        $this->assertTrue($this->isUnique('contacts', 'email'));
        $this->assertTrue($this->isUnique('companies', 'name'));
        $this->assertTrue($this->isUnique('companies', 'company_code'));
    }

    public function test_duplicate_user_email_is_rejected_by_the_database(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);

        $this->expectException(QueryException::class);
        User::factory()->create(['email' => 'dup@example.com']);
    }

    public function test_deleting_a_company_with_contacts_is_blocked(): void
    {
        $contact = Contact::factory()->create();

        $this->expectException(QueryException::class);
        $contact->company->delete();
    }

    public function test_ticket_comment_without_author_is_rejected_by_check_constraint(): void
    {
        $contact = Contact::factory()->create();
        DB::table('tickets')->insert([
            'ticket_no' => 'TKT-0001',
            'company_id' => $contact->company_id,
            'contact_id' => $contact->contact_id,
            'title' => 'Check test',
        ]);
        $ticketId = DB::table('tickets')->value('ticket_id');

        $this->expectException(QueryException::class);
        DB::table('ticket_comments')->insert(['ticket_id' => $ticketId, 'message' => 'orphan']);
    }

    public function test_ticket_comment_with_an_author_is_accepted(): void
    {
        $contact = Contact::factory()->create();
        DB::table('tickets')->insert([
            'ticket_no' => 'TKT-0001',
            'company_id' => $contact->company_id,
            'contact_id' => $contact->contact_id,
            'title' => 'Check test',
        ]);
        $ticketId = DB::table('tickets')->value('ticket_id');

        DB::table('ticket_comments')->insert([
            'ticket_id' => $ticketId, 'contact_id' => $contact->contact_id, 'message' => 'hello',
        ]);

        $this->assertDatabaseCount('ticket_comments', 1);
    }
}
