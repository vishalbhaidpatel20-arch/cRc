<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

/**
 * Second line of defence behind the Ticket global scope.
 * Admin: everything. Agent: only the tickets assigned to them, and never "assign".
 */
class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() || $ticket->assigned_to === $user->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    /** Assign and reassign are admin only. */
    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin();
    }
}
