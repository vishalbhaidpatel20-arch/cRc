<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A reminder created from a logged conversation ("Follow up on ..."). */
class FollowUp extends Model
{
    protected $table = 'follow_ups';

    protected $primaryKey = 'follow_up_id';

    public $timestamps = false;

    protected $fillable = ['contact_id', 'interaction_id', 'assigned_to', 'due_date', 'description', 'status', 'completed_at'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'completed_at' => 'datetime'];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id', 'contact_id');
    }

    public function interaction(): BelongsTo
    {
        return $this->belongsTo(Interaction::class, 'interaction_id', 'interaction_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to', 'user_id');
    }

    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->due_date->lt(today());
    }

    /** "today", "tomorrow", "yesterday" or "on 20 Oct". */
    public function dueLabel(): string
    {
        $d = $this->due_date;

        return match (true) {
            $d->isToday() => 'today',
            $d->isTomorrow() => 'tomorrow',
            $d->isYesterday() => 'yesterday',
            default => 'on '.$d->format($d->isCurrentYear() ? 'j M' : 'j M Y'),
        };
    }
}
