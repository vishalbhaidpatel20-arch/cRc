<?php

namespace App\Services;

use App\Models\FollowUp;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deleting an agent is a SOFT delete (User uses SoftDeletes): deleted_at is set and the row keeps its name, email and phone,
 * because conversations, follow-ups and assignment history must keep their author (foreign keys RESTRICT; history is
 * append-only) and an admin may restore the account. A soft-deleted user is invisible to normal queries, so they cannot sign
 * in and no longer appear in lists or pickers. Their work is handed back: tickets they still held go to the unassigned queue,
 * pending follow-ups are cancelled and their sessions end. Their private plans, notifications and company links are kept
 * (nobody else can see them) so a restore brings them back; is_active is cleared too, so no reminder is sent to them.
 */
class AccountRemoval
{
    /** @throws \InvalidArgumentException when the user is not an agent or was already removed */
    public function remove(User $agent, User $actor): void
    {
        DB::transaction(function () use ($agent, $actor) {
            $locked = User::whereKey($agent->user_id)->lockForUpdate()->firstOrFail();

            if (! $locked->isAgent() || $locked->isRemoved()) {
                throw new \InvalidArgumentException('Only an agent account that still exists can be deleted.');
            }

            // Unresolved tickets go back to the queue, with a history row saying why. Resolved ones keep their last holder.
            Ticket::withoutGlobalScopes()
                ->where('assigned_to', $locked->user_id)
                ->where('status', '!=', 'resolved')
                ->get()
                ->each(function (Ticket $ticket) use ($locked, $actor) {
                    TicketAssignment::create([
                        'ticket_id' => $ticket->ticket_id,
                        'from_user' => $locked->user_id,
                        'to_user' => null,
                        'assigned_by' => $actor->user_id,
                        'reason' => 'Agent account deleted',
                        'assigned_at' => now(),
                    ]);
                    $ticket->update(['assigned_to' => null]);
                });

            // Nobody is left to do their pending follow-ups; the rows stay as history.
            FollowUp::where('assigned_to', $locked->user_id)->where('status', 'pending')->update(['status' => 'cancelled']);

            DB::table(config('session.table', 'sessions'))->where('user_id', $locked->user_id)->delete();

            $locked->forceFill(['is_active' => false])->save();
            $locked->delete();   // soft delete: sets deleted_at, keeps the details for history and a possible restore
        });
    }
}
