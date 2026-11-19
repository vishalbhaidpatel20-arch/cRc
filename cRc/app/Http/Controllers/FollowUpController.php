<?php

namespace App\Http\Controllers;

use App\Models\FollowUp;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FollowUpController extends Controller
{
    /** The filters of the list, in the order of the dropdown. */
    public const FILTERS = [
        'pending' => 'Pending (all open)',
        'overdue' => 'Overdue',
        'due' => 'Due today or overdue',
        'today' => 'Due today',
        'upcoming' => 'Upcoming',
        'done' => 'Done',
        'all' => 'All',
    ];

    /** Agents see only their own follow-ups; admins see everyone's (and can narrow to one agent). */
    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = array_key_exists((string) $request->query('filter'), self::FILTERS) ? $request->query('filter') : 'pending';
        $term = trim((string) $request->query('q'));
        $agent = $request->query('agent');

        $followUps = FollowUp::query()
            ->with(['contact.company', 'assignee'])
            ->when(! $user->isAdmin(), fn ($q) => $q->where('assigned_to', $user->user_id))
            ->when($user->isAdmin() && ctype_digit((string) $agent), fn ($q) => $q->where('assigned_to', (int) $agent))
            ->when($filter === 'pending', fn ($q) => $q->where('status', 'pending'))
            ->when($filter === 'overdue', fn ($q) => $q->where('status', 'pending')->whereDate('due_date', '<', today()))
            ->when($filter === 'due', fn ($q) => $q->where('status', 'pending')->whereDate('due_date', '<=', today()))
            ->when($filter === 'today', fn ($q) => $q->where('status', 'pending')->whereDate('due_date', today()))
            ->when($filter === 'upcoming', fn ($q) => $q->where('status', 'pending')->whereDate('due_date', '>', today()))
            ->when($filter === 'done', fn ($q) => $q->where('status', 'done'))
            ->when($term !== '', function ($query) use ($term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn ($q) => $q->where('description', 'like', $like)
                    ->orWhereHas('contact', fn ($c) => $c->where('full_name', 'like', $like)
                        ->orWhereHas('company', fn ($co) => $co->where('name', 'like', $like))));
            })
            ->when($filter === 'done', fn ($q) => $q->orderByDesc('completed_at'), fn ($q) => $q->orderBy('due_date'))
            ->orderBy('follow_up_id')
            ->paginate(config('crc.per_page'))
            ->withQueryString();

        return view('follow-ups.index', [
            'followUps' => $followUps,
            'filter' => $filter,
            'filters' => self::FILTERS,
            'agents' => $user->isAdmin() ? User::where('role', User::ROLE_AGENT)->orderBy('full_name')->get(['user_id', 'full_name']) : collect(),
        ]);
    }

    /** Only the agent a follow-up belongs to, or an admin, can mark it done. */
    public function done(Request $request, FollowUp $followUp): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $followUp->assigned_to === $request->user()->user_id, 403);

        if ($followUp->status === 'pending') {
            $followUp->update(['status' => 'done', 'completed_at' => now()]);
        }

        return back()->with('status', 'Follow-up marked done.');
    }
}
