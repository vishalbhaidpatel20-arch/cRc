<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\FollowUp;
use App\Models\Interaction;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The daily conversation log. Every agent can log a conversation with any contact and read
 * the log (project scope); what an agent can LINK a conversation to is limited to tickets
 * they can see (the Ticket global scope), so nobody can attach notes to another agent's ticket.
 * Entries are append-only: no edit, no delete.
 */
class InteractionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $from = $this->dateOrNull($request->query('from'));
        $to = $this->dateOrNull($request->query('to'));

        $interactions = Interaction::query()
            ->with(['contact.company', 'user', 'ticket', 'followUp'])
            ->search($request->query('q'))
            ->when(in_array($request->query('channel'), Interaction::CHANNELS, true), fn ($q) => $q->where('interactions.channel', $request->query('channel')))
            ->when(in_array($request->query('topic'), Interaction::TOPICS, true), fn ($q) => $q->where('interactions.topic', $request->query('topic')))
            ->when($request->filled('contact_id'), fn ($q) => $q->where('interactions.contact_id', (int) $request->query('contact_id')))
            ->when($request->boolean('mine'), fn ($q) => $q->where('interactions.user_id', $user->user_id))
            ->when($user->isAdmin() && ctype_digit((string) $request->query('user_id')), fn ($q) => $q->where('interactions.user_id', (int) $request->query('user_id')))
            ->when($from, fn ($q) => $q->where('interactions.interaction_at', '>=', $from->startOfDay()))
            ->when($to, fn ($q) => $q->where('interactions.interaction_at', '<=', $to->endOfDay()))
            ->orderByDesc('interactions.interaction_at')
            ->orderByDesc('interactions.interaction_id')
            ->paginate(config('crc.per_page'))
            ->withQueryString();

        return view('interactions.index', [
            'interactions' => $interactions,
            'users' => $user->isAdmin() ? User::where('is_active', 1)->orderBy('full_name')->get(['user_id', 'full_name']) : collect(),
        ]);
    }

    /** Log a conversation from a ticket screen. The ticket is fixed; the contact is from the ticket's company. */
    public function storeForTicket(Request $request, Ticket $ticket): RedirectResponse
    {
        Gate::authorize('view', $ticket);

        $data = $this->validated($request);
        $contact = Contact::where('company_id', $ticket->company_id)->where('is_active', 1)
            ->find($request->input('contact_id'));

        if (! $contact) {
            throw ValidationException::withMessages(['contact_id' => 'Choose an active contact from this ticket\'s company.']);
        }

        $this->record($request->user(), $contact, $ticket, $data);

        return redirect()->route('tickets.show', $ticket)->withFragment('conversations')
            ->with('status', 'Conversation logged.');
    }

    /** Log a conversation from a contact screen. The ticket link is optional. */
    public function storeForContact(Request $request, Contact $contact): RedirectResponse
    {
        if (! $contact->is_active) {
            throw ValidationException::withMessages(['summary' => 'This contact is inactive.']);
        }

        $data = $this->validated($request);
        $ticket = null;

        if ($request->filled('ticket_id')) {
            // Looked up through the Ticket model on purpose, so the visibility scope applies:
            // an agent cannot link a conversation to a ticket they cannot see.
            $ticket = Ticket::where('company_id', $contact->company_id)->find($request->input('ticket_id'));

            if (! $ticket) {
                throw ValidationException::withMessages(['ticket_id' => 'Choose one of your tickets for this contact\'s company.']);
            }
        }

        $this->record($request->user(), $contact, $ticket, $data);

        return redirect()->route('contacts.show', $contact)->withFragment('conversations')
            ->with('status', 'Conversation logged.');
    }

    private function record(User $user, Contact $contact, ?Ticket $ticket, array $data): Interaction
    {
        return DB::transaction(function () use ($user, $contact, $ticket, $data) {
            // user_id always comes from the session, never from the request.
            $interaction = Interaction::create([
                'contact_id' => $contact->contact_id,
                'user_id' => $user->user_id,
                'ticket_id' => $ticket?->ticket_id,
                'topic' => $data['topic'],
                'channel' => $data['channel'],
                'direction' => $data['direction'],
                'summary' => $data['summary'],
                'interaction_at' => $data['interaction_at'] ?? now(),
            ]);

            // "Follow up on <date>": a pending reminder for the agent who logged it.
            if (! empty($data['follow_up_on'])) {
                FollowUp::create([
                    'contact_id' => $contact->contact_id,
                    'interaction_id' => $interaction->interaction_id,
                    'assigned_to' => $user->user_id,
                    'due_date' => $data['follow_up_on'],
                    'description' => 'Follow up: '.Str::limit(preg_replace('/\s+/', ' ', $data['summary']), 200, '...'),
                    'status' => 'pending',
                ]);
            }

            return $interaction;
        });
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'topic' => ['required', Rule::in(Interaction::TOPICS)],
            'channel' => ['required', Rule::in(Interaction::CHANNELS)],
            'direction' => ['required', Rule::in(Interaction::DIRECTIONS)],
            'summary' => ['required', 'string', 'max:5000'],
            'interaction_at' => ['nullable', 'date', 'before_or_equal:now'],
            'follow_up_on' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
    }

    private function dateOrNull(?string $value): ?\Illuminate\Support\Carbon
    {
        try {
            return $value ? \Illuminate\Support\Carbon::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
