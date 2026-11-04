<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FollowUp;
use App\Models\Interaction;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Admin-only (the route is behind role:admin). Shows the unassigned queue and each agent's
 * workload so the admin can balance the team. Admins are not filtered by the Ticket scope.
 */
class DashboardController extends Controller
{
    private const QUEUE_LIMIT = 10;

    public function __invoke(): View
    {
        $workload = $this->workload();

        return view('admin.dashboard', [
            'cards' => [
                'open' => Ticket::open()->count(),
                'unassigned' => Ticket::open()->whereNull('tickets.assigned_to')->count(),
                'critical' => Ticket::open()->where('tickets.priority', 'critical')->count(),
                'overdue' => Ticket::open()->where('tickets.due_at', '<', now())->count(),
                'conversations_today' => Interaction::where('interaction_at', '>=', today())->count(),
                'followups_overdue' => FollowUp::where('status', 'pending')->where('due_date', '<', today())->count(),
            ],
            'queue' => Ticket::open()->whereNull('tickets.assigned_to')
                ->with(['company', 'contact'])
                ->byPriority()->orderBy('tickets.created_at')
                ->limit(self::QUEUE_LIMIT)->get(),
            'queueTotal' => Ticket::open()->whereNull('tickets.assigned_to')->count(),
            // Open tickets split by priority and by status (the two breakdown panels), and the latest conversations.
            'byPriority' => Ticket::open()->selectRaw('tickets.priority, count(*) as total')->groupBy('tickets.priority')->pluck('total', 'priority'),
            'byStatus' => Ticket::open()->selectRaw('tickets.status, count(*) as total')->groupBy('tickets.status')->pluck('total', 'status'),
            'recent' => Interaction::with(['contact', 'user', 'ticket', 'followUp'])->latest('interaction_at')->latest('interaction_id')->limit(5)->get(),
            'workload' => $workload,
            'maxOpen' => max(1, (int) $workload->max('open_total')),
            'assignable' => $workload->where('is_active', 1)->values(),
        ]);
    }

    /**
     * One row per agent: open tickets in total and per priority, plus how many are overdue.
     * Agents with no open tickets are included (LEFT JOIN, the condition lives in ON), and so is
     * a deactivated agent who still holds open tickets, so those never get forgotten.
     */
    private function workload(): Collection
    {
        return User::query()
            ->where('users.role', User::ROLE_AGENT)
            ->leftJoin('tickets', function ($join) {
                $join->on('tickets.assigned_to', '=', 'users.user_id')
                    ->where('tickets.status', '!=', 'resolved');
            })
            ->groupBy('users.user_id', 'users.full_name', 'users.is_active')
            ->havingRaw('users.is_active = 1 OR COUNT(tickets.ticket_id) > 0')
            ->selectRaw(
                'users.user_id, users.full_name, users.is_active,
                 COUNT(tickets.ticket_id) AS open_total,
                 COALESCE(SUM(tickets.priority = ?), 0) AS critical,
                 COALESCE(SUM(tickets.priority = ?), 0) AS high,
                 COALESCE(SUM(tickets.priority = ?), 0) AS medium,
                 COALESCE(SUM(tickets.priority = ?), 0) AS low,
                 COALESCE(SUM(tickets.due_at < ?), 0) AS overdue',
                ['critical', 'high', 'medium', 'low', now()]
            )
            ->orderByDesc('open_total')->orderBy('users.full_name')
            ->get();
    }
}
