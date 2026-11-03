<?php

namespace App\Models;

use Database\Factories\MessageTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{
    /** @use HasFactory<MessageTemplateFactory> */
    use HasFactory;

    public const CATEGORIES = ['query', 'bug', 'customization', 'follow_up', 'general'];

    public const CHANNELS = ['email', 'whatsapp'];

    /** The only placeholders a template may use: {{name}}, {{company}}, ... */
    public const PLACEHOLDERS = [
        'name' => "the contact's full name",
        'company' => "the contact's company",
        'ticket_no' => 'the ticket number (when used from a ticket)',
        'ticket_title' => 'the ticket title (when used from a ticket)',
        'agent_name' => 'the logged-in agent',
    ];

    /** Display form of a placeholder name, e.g. token('name') gives "{{name}}". */
    public static function token(string $key): string
    {
        return '{'.'{'.$key.'}'.'}';
    }

    protected $table = 'message_templates';

    protected $primaryKey = 'template_id';

    public $timestamps = false;

    protected $fillable = ['name', 'category', 'channel', 'subject', 'body', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
