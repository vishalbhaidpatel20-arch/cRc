<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\FollowUp;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "Deleting" an agent. The row itself stays, because conversations, follow-ups and assignment history must keep their author
 * (foreign keys RESTRICT; history is append-only). What is removed is the person: the account can no longer sign in, their
 * name, email and phone are wiped (the row reads "Deleted agent #12"), their private plans, notifications, sessions and company
 * links are deleted, and the tickets they still held go back to the unassigned queue.
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

            CalendarEvent::where('user_id', $locked->user_id)->delete();
            $locked->notifications()->delete();
            $locked->companies()->detach();
            DB::table(config('session.table', 'sessions'))->where('user_id', $locked->user_id)->delete();

            $locked->forceFill([
                'full_name' => 'Deleted agent #'.$locked->user_id,
                'email' => User::removedEmail($locked->user_id),
                'phone' => null,
                'password_hash' => null,
                'all_companies' => false,
                'is_active' => false,
            ])->save();
        });
    }
}
