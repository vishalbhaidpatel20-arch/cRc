<?php

namespace Tests\Unit\Models;

use App\Models\MessageTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MessageTemplateTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private function enumValues(string $column): array
    {
        $type = DB::selectOne(
            'SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [DB::getDatabaseName(), 'message_templates', $column]
        )->t;
        preg_match_all("/'([^']*)'/", $type, $m);

        return $m[1];
    }

    public function test_categories_match_database_enum(): void
    {
        $this->assertSame($this->enumValues('category'), MessageTemplate::CATEGORIES);
    }

    public function test_channels_match_database_enum(): void
    {
        $this->assertSame($this->enumValues('channel'), MessageTemplate::CHANNELS);
    }

    public function test_placeholder_keys(): void
    {
        $this->assertSame(
            ['name', 'company', 'ticket_no', 'ticket_title', 'agent_name'],
            array_keys(MessageTemplate::PLACEHOLDERS)
        );
    }

    public function test_every_placeholder_has_a_description(): void
    {
        foreach (MessageTemplate::PLACEHOLDERS as $key => $description) {
            $this->assertNotSame('', trim($description), "placeholder $key");
        }
    }

    public function test_token_wraps_key_in_double_braces(): void
    {
        $this->assertSame('{{name}}', MessageTemplate::token('name'));
        $this->assertSame('{{ticket_no}}', MessageTemplate::token('ticket_no'));
    }

    public function test_token_of_every_placeholder_is_well_formed(): void
    {
        foreach (array_keys(MessageTemplate::PLACEHOLDERS) as $key) {
            $this->assertSame('{{'.$key.'}}', MessageTemplate::token($key));
        }
    }

    public function test_is_active_is_cast_to_boolean(): void
    {
        $active = MessageTemplate::factory()->create()->fresh();
        $inactive = MessageTemplate::factory()->inactive()->create()->fresh();

        $this->assertTrue($active->is_active);
        $this->assertFalse($inactive->is_active);
    }

    public function test_table_key_and_timestamps_configuration(): void
    {
        $model = new MessageTemplate;

        $this->assertSame('message_templates', $model->getTable());
        $this->assertSame('template_id', $model->getKeyName());
        $this->assertFalse($model->usesTimestamps());
    }

    public function test_fillable_attributes(): void
    {
        $this->assertSame(
            ['name', 'category', 'channel', 'subject', 'body', 'is_active'],
            (new MessageTemplate)->getFillable()
        );
    }

    public function test_whatsapp_template_has_no_subject(): void
    {
        $this->assertNull(MessageTemplate::factory()->whatsapp()->create()->fresh()->subject);
    }
}
