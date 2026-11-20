<?php

namespace App\Services;

use App\Jobs\SendTicketCompletedNotification;
use App\Models\Ticket;
use App\Models\User;

/**
 * One rule for "an agent finished their ticket", shared by the ticket Edit form and the conversation log form.
 * Only when the ticket just became "resolved" and the actor is the agent who holds it. The job
 * picks the admin to tell (the one who made the latest assignment to that agent, else every active admin).
 */
class TicketCompletion
{
    public function afterStatusChange(Ticket $ticket, string $oldStatus, User $actor): void
    {
        if ($oldStatus === 'resolved' || $ticket->status !== 'resolved') {
            return;
        }

        if ($actor->isAdmin() || $ticket->assigned_to !== $actor->user_id) {
            return;
        }

        SendTicketCompletedNotification::dispatch($ticket->ticket_id, $actor->user_id)->afterCommit();
    }
}
