<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Interaction;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\User;
use App\Notifications\TicketAssigned;
use App\Services\TemplateRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Visibility is enforced twice: the Ticket global scope (agents only ever load their own
 * tickets, so another agent's URL is a 404) and TicketPolicy (explicit checks below).
 * Only admins may assign/reassign. Tickets are never deleted.
 */
class TicketController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): View
    {
        $user = $request->user();
        $status = $request->query('status', 'open');
        $priority = $request->query('priority');
        $assigned = $request->query('assigned');

        $tickets = Ticket::query()
            ->with(['company', 'contact', 'assignee'])
            ->search($request->query('q'))
            ->when($status === 'open', fn ($q) => $q->open())
            ->when(in_array($status, Ticket::STATUSES, true), fn ($q) => $q->where('tickets.status', $status))
            ->when(in_array($priority, Ticket::PRIORITIES, true), fn ($q) => $q->where('tickets.priority', $priority))
            ->when($request->filled('company_id'), fn ($q) => $q->where('tickets.company_id', $request->query('company_id')))
            ->when($request->boolean('overdue'), fn ($q) => $q->open()->where('tickets.due_at', '<', now()))
            // Assignee filter is an admin tool; for agents the global scope already pins it to themselves.
            ->when($user->isAdmin() && $assigned === 'unassigned', fn ($q) => $q->whereNull('tickets.assigned_to'))
            ->when($user->isAdmin() && ctype_digit((string) $assigned), fn ($q) => $q->where('tickets.assigned_to', (int) $assigned))
            ->byPriority()
            ->orderBy('tickets.created_at')
            ->orderBy('tickets.ticket_id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('tickets.index', [
            // Blank ticket for the "New ticket" pop-up; ?new=1&contact_id=5 pre-selects the contact.
            'newTicket' => new Ticket(['topic' => 'query', 'priority' => 'medium', 'contact_id' => $request->boolean('new') ? $request->query('contact_id') : null]),
            'contactsByCompany' => $this->contactsByCompany(),
            'tickets' => $tickets,
            'status' => $status,
            'companies' => Company::orderBy('name')->get(['company_id', 'name']),
            'agents' => $user->isAdmin() ? $this->assignableAgents() : collect(),
        ]);
    }

    public function show(Request $request, Ticket $ticket, TemplateRenderer $renderer): View
    {
        Gate::authorize('view', $ticket);

        $ticket->load(['company', 'contact.company', 'assignee']);

        return view('tickets.show', $renderer->panelData($request, $ticket->contact, $ticket) + [
            'interactions' => Interaction::with(['contact', 'user'])->where('ticket_id', $ticket->ticket_id)
                ->orderBy('interaction_at')->orderBy('interaction_id')->get(),
            'panelContacts' => Contact::where('company_id', $ticket->company_id)->where('is_active', 1)
                ->orderBy('full_name')->get(['contact_id', 'full_name']),
            'ticket' => $ticket,
            'history' => $ticket->assignments()->with(['fromUser', 'toUser', 'assignedBy'])
                ->orderBy('assigned_at')->orderBy('assignment_id')->get(),
            'agents' => auth()->user()->isAdmin() ? $this->assignableAgents() : collect(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Ticket::class);

        return view('tickets.form', [
            'ticket' => new Ticket(['topic' => 'query', 'priority' => 'medium', 'contact_id' => $request->query('contact_id')]),
            'contactsByCompany' => $this->contactsByCompany(),
            'agents' => $request->user()->isAdmin() ? $this->assignableAgents() : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Ticket::class);

        $user = $request->user();

        $data = $request->validate([
            'contact_id' => ['required', 'integer', Rule::exists('contacts', 'contact_id')->where('is_active', 1)],
            'topic' => ['required', Rule::in(Ticket::TOPICS)],
            'priority' => ['required', Rule::in(Ticket::PRIORITIES)],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', $this->assignableAgentRule()],
        ]);

        // An agent can never pick the assignee: their own ticket is assigned to themselves,
        // otherwise they would lose sight of what they just logged.
        $assigneeId = $user->isAdmin() ? ($data['assigned_to'] ?? null) : $user->user_id;

        $ticket = DB::transaction(function () use ($data, $user, $assigneeId) {
            $contact = Contact::findOrFail($data['contact_id']);

            // The company always comes from the contact, so the two can never disagree.
            $ticket = Ticket::create([
                'ticket_no' => 'TMP'.Str::random(12),
                'company_id' => $contact->company_id,
                'contact_id' => $contact->contact_id,
                'assigned_to' => $assigneeId,
                'topic' => $data['topic'],
                'priority' => $data['priority'],
                'status' => 'open',
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'due_at' => $data['due_at'] ?? null,
            ]);
            $ticket->update(['ticket_no' => sprintf('TKT-%04d', $ticket->ticket_id)]);

            if ($assigneeId !== null) {
                $reason = $user->isAdmin() ? 'Assigned at creation' : 'Created by the agent';
                $this->recordAssignment($ticket, null, $assigneeId, $user, $reason);

                if ($assigneeId !== $user->user_id) {
                    User::findOrFail($assigneeId)->notify(new TicketAssigned($ticket, $user, $reason));
                }
            }

            return $ticket;
        });

        // Back to the ticket list, on the page where the new ticket sits, highlighted.
        $page = intdiv($this->ticketsBefore($ticket), self::PER_PAGE) + 1;

        return redirect()->route('tickets.index', $page > 1 ? ['page' => $page] : [])
            ->with('status', "Ticket {$ticket->ticket_no} created.")
            ->with('highlight', $ticket->ticket_id);
    }

    public function edit(Ticket $ticket): View
    {
        Gate::authorize('update', $ticket);

        return view('tickets.form', [
            'ticket' => $ticket->load('contact.company'),
            'contactsByCompany' => collect(),
            'agents' => collect(),
        ]);
    }

    public function update(Request $request, Ticket $ticket): RedirectResponse
    {
        Gate::authorize('update', $ticket);

        // Company, contact and assignee are deliberately not editable here.
        $data = $request->validate([
            'topic' => ['required', Rule::in(Ticket::TOPICS)],
            'priority' => ['required', Rule::in(Ticket::PRIORITIES)],
            'status' => ['required', Rule::in(Ticket::STATUSES)],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date'],
        ]);

        $ticket->update($data);

        return redirect()->route('tickets.show', $ticket)->with('status', 'Ticket updated.');
    }

    /** Admin only: assign an unassigned ticket or move it to another agent. */
    public function assign(Request $request, Ticket $ticket): RedirectResponse
    {
        Gate::authorize('assign', $ticket);

        $data = $request->validate([
            'assigned_to' => ['required', 'integer', $this->assignableAgentRule()],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $admin = $request->user();
        $newAssignee = User::findOrFail($data['assigned_to']);

        $changed = DB::transaction(function () use ($ticket, $newAssignee, $admin, $data) {
            // Lock the row so two admins reassigning at once cannot interleave.
            $locked = Ticket::withoutGlobalScopes()->lockForUpdate()->findOrFail($ticket->ticket_id);

            if ($locked->assigned_to === $newAssignee->user_id) {
                return false;
            }

            $this->recordAssignment($locked, $locked->assigned_to, $newAssignee->user_id, $admin, $data['reason'] ?? null);
            $locked->update(['assigned_to' => $newAssignee->user_id]);
            $newAssignee->notify(new TicketAssigned($locked, $admin, $data['reason'] ?? null));

            return true;
        });

        if (! $changed) {
            return back()->withErrors(['assigned_to' => "{$newAssignee->full_name} already has this ticket."]);
        }

        // Assigning from the dashboard queue returns to the dashboard (only this one value is honoured).
        $target = $request->input('from') === 'dashboard' ? route('admin.dashboard') : route('tickets.show', $ticket);

        return redirect($target)->with('status', "{$ticket->ticket_no} assigned to {$newAssignee->full_name}.");
    }

    /**
     * How many tickets the default list (open ones, priority first, then oldest, then id) shows before
     * this one. Uses the Ticket scope, so for an agent it only counts their own tickets.
     */
    private function ticketsBefore(Ticket $ticket): int
    {
        $rank = ['critical' => 1, 'high' => 2, 'medium' => 3, 'low' => 4][$ticket->priority];
        $field = "FIELD(tickets.priority, 'critical', 'high', 'medium', 'low')";

        return Ticket::open()->where(function ($q) use ($field, $rank, $ticket) {
            $q->whereRaw("$field < ?", [$rank])
                ->orWhere(function ($q) use ($field, $rank, $ticket) {
                    $q->whereRaw("$field = ?", [$rank])->where(function ($q) use ($ticket) {
                        $q->where('tickets.created_at', '<', $ticket->created_at)
                            ->orWhere(fn ($q) => $q->where('tickets.created_at', $ticket->created_at)->where('tickets.ticket_id', '<', $ticket->ticket_id));
                    });
                });
        })->count();
    }

    private function recordAssignment(Ticket $ticket, ?int $from, ?int $to, User $by, ?string $reason): void
    {
        TicketAssignment::create([
            'ticket_id' => $ticket->ticket_id,
            'from_user' => $from,
            'to_user' => $to,
            'assigned_by' => $by->user_id,
            'reason' => $reason,
            'assigned_at' => now(),
        ]);
    }

    /** Only active agents can receive tickets. */
    private function assignableAgentRule()
    {
        return Rule::exists('users', 'user_id')->where('role', User::ROLE_AGENT)->where('is_active', 1);
    }

    private function assignableAgents()
    {
        return User::where('role', User::ROLE_AGENT)->where('is_active', 1)->orderBy('full_name')->get(['user_id', 'full_name']);
    }

    /** Active contacts of active companies, grouped by company name for the dropdown. */
    private function contactsByCompany()
    {
        return Contact::with('company')
            ->where('is_active', 1)
            ->whereHas('company', fn ($q) => $q->where('is_active', 1))
            ->orderBy('full_name')
            ->get()
            ->groupBy(fn ($c) => $c->company->name)
            ->sortKeys();
    }
}
