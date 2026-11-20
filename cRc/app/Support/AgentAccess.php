<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * What a signed-in AGENT may see, derived from the tickets an admin assigned to them. Used by the global scopes on
 * Company, Contact and Interaction (the Ticket model has its own scope: only tickets assigned to the agent).
 *
 *  - Companies: those with at least one ticket assigned to the agent.
 *  - Contacts: the contacts of those companies (the people at the client the agent works for).
 *  - Conversations: those on the agent's tickets, plus the ones the agent logged with a contact they can still see.
 *
 * Admins, and code with nobody signed in (queue workers, console, seeders), are never filtered. Reassigning a ticket moves
 * the access with it. Raw DB::table() queries and Rule::exists() bypass these scopes: never use them for agent requests.
 */
class AgentAccess
{
    /** The signed-in agent, or null when nothing should be filtered. */
    public static function agent(): ?User
    {
        $user = Auth::user();

        return $user instanceof User && $user->isAgent() ? $user : null;
    }

    /** Subquery: ids of the agent's companies (those of their tickets). */
    public static function companyIds(User $agent): \Closure
    {
        return fn ($q) => $q->select('company_id')->from('tickets')->where('assigned_to', $agent->user_id);
    }

    public static function scopeCompanies(Builder $query, User $agent): void
    {
        $query->whereIn($query->getModel()->getTable().'.company_id', self::companyIds($agent));
    }

    public static function scopeContacts(Builder $query, User $agent): void
    {
        $query->whereIn($query->getModel()->getTable().'.company_id', self::companyIds($agent));
    }

    public static function scopeInteractions(Builder $query, User $agent): void
    {
        $table = $query->getModel()->getTable();

        $query->where(function ($q) use ($table, $agent) {
            $q->whereIn($table.'.ticket_id', fn ($s) => $s->select('ticket_id')->from('tickets')->where('assigned_to', $agent->user_id))
                ->orWhere(function ($q) use ($table, $agent) {
                    $q->where($table.'.user_id', $agent->user_id)
                        ->whereIn($table.'.contact_id', fn ($s) => $s->select('contact_id')->from('contacts')
                            ->whereIn('company_id', self::companyIds($agent)));
                });
        });
    }
}
