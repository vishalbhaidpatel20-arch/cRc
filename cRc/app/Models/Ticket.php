<?php

namespace App\Models;

use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    public const TOPICS = ['query', 'bug', 'customization'];

    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    public const STATUSES = ['open', 'in_progress', 'waiting', 'resolved'];

    protected $primaryKey = 'ticket_id';

    protected $fillable = [
        'ticket_no', 'company_id', 'contact_id', 'assigned_to', 'topic', 'priority',
        'status', 'title', 'description', 'due_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Agent-only visibility. This is the single place where the rule lives:
     * a logged-in agent only ever sees tickets assigned to them (lists, search,
     * counts and route-model binding all go through it). Admins, and code
     * running with nobody logged in (console, seeders), are not filtered.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('agent_visibility', function (Builder $query) {
            $user = Auth::user();

            if ($user && $user->isAgent()) {
                $query->where($query->getModel()->getTable().'.assigned_to', $user->user_id);
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id', 'contact_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to', 'user_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TicketAssignment::class, 'ticket_id', 'ticket_id');
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null && $this->status !== 'resolved' && $this->due_at->isPast();
    }

    /** Anything not resolved. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('tickets.status', '!=', 'resolved');
    }

    /** critical, high, medium, low. Combine with ->orderBy('tickets.created_at') for the queue order. */
    public function scopeByPriority(Builder $query): Builder
    {
        return $query->orderByRaw("FIELD(tickets.priority, 'critical', 'high', 'medium', 'low')");
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }
        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(fn ($q) => $q
            ->where('tickets.ticket_no', 'like', $like)
            ->orWhere('tickets.title', 'like', $like)
            ->orWhere('tickets.description', 'like', $like));
    }
}
