<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        // The Ticket global scope already limits these queries to this agent's tickets.
        return view('agent.dashboard', [
            'openCount' => Ticket::open()->count(),
            'byPriority' => Ticket::open()->selectRaw('priority, count(*) as total')->groupBy('priority')->pluck('total', 'priority'),
            'topTickets' => Ticket::open()->with(['company', 'contact'])->byPriority()->orderBy('tickets.created_at')->limit(5)->get(),
            'notifications' => $request->user()->unreadNotifications()->latest()->limit(10)->get(),
        ]);
    }
}
