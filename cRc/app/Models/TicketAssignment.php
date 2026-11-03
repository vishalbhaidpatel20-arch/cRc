<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One row per (re)assignment: the permanent "who handled this ticket" history. */
class TicketAssignment extends Model
{
    protected $table = 'ticket_assignments';

    protected $primaryKey = 'assignment_id';

    public $timestamps = false;

    protected $fillable = ['ticket_id', 'from_user', 'to_user', 'assigned_by', 'reason', 'assigned_at'];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id')->withoutGlobalScopes();
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user', 'user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user', 'user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by', 'user_id');
    }
}
