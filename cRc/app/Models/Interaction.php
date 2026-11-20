<?php

namespace App\Models;

use App\Support\AgentAccess;
use Database\Factories\InteractionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One logged conversation with a client contact. It is an append-only log: there is no
 * edit or delete. The DB trigger keeps contacts.last_contacted_at current.
 */
class Interaction extends Model
{
    /** @use HasFactory<InteractionFactory> */
    use HasFactory;

    public const TOPICS = ['query', 'bug', 'customization', 'follow_up', 'general'];

    public const CHANNELS = ['call', 'whatsapp', 'email', 'meeting'];

    public const DIRECTIONS = ['inbound', 'outbound'];

    protected $primaryKey = 'interaction_id';

    public $timestamps = false;

    protected $fillable = [
        'contact_id', 'user_id', 'ticket_id', 'topic', 'channel', 'direction', 'summary', 'interaction_at',
    ];

    protected function casts(): array
    {
        return ['interaction_at' => 'datetime'];
    }

    /** Display name of a channel: "Call", "Email", "WhatsApp", "Meeting". */
    public static function channelLabel(string $channel): string
    {
        return $channel === 'whatsapp' ? 'WhatsApp' : ucfirst($channel);
    }

    /**
     * Agents only see conversations on their own tickets, plus the ones they logged with a contact they can still see
     * (App\Support\AgentAccess). Admins see the whole log.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('agent_visibility', function (Builder $query) {
            if ($agent = AgentAccess::agent()) {
                AgentAccess::scopeInteractions($query, $agent);
            }
        });
    }

    /** Unscoped: the contact of a conversation the viewer may see (never null in a view). */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id', 'contact_id')->withoutGlobalScope('agent_visibility');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id')->withTrashed();   // the log keeps a deleted author
    }

    /**
     * Goes through the Ticket global scope on purpose: for an agent this is null when the
     * conversation is linked to a ticket that is not theirs.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }

    public function followUp(): HasOne
    {
        return $this->hasOne(FollowUp::class, 'interaction_id', 'interaction_id');
    }

    /** "Today, 11:20", "Yesterday, 10:45", "18 Oct", or "18 Oct 2025" for older years. */
    public function whenLabel(): string
    {
        $t = $this->interaction_at;

        return match (true) {
            $t->isToday() => 'Today, '.$t->format('H:i'),
            $t->isYesterday() => 'Yesterday, '.$t->format('H:i'),
            $t->isCurrentYear() => $t->format('j M'),
            default => $t->format('j M Y'),
        };
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }
        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(fn ($q) => $q
            ->where('interactions.summary', 'like', $like)
            ->orWhereHas('contact', fn ($c) => $c->where('full_name', 'like', $like)));
    }
}
