<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\FollowUp;
use App\Models\Ticket;
use App\Support\UserTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The calendar: tickets by due date (overdue in red), the user's follow-ups and their own plans, for admins and agents alike.
 * Plans are personal: every query and every change goes through the signed-in user, nobody can see or touch someone else's.
 * Tickets follow the usual rule (the Ticket scope: agents see only theirs, admins all).
 */
class CalendarController extends Controller
{
    private const ALL_DAY_REMINDER_HOUR = '09:00';

    public function index(Request $request): View
    {
        $user = $request->user();
        $tz = UserTime::zone($request);
        $today = CarbonImmutable::now($tz)->startOfDay();

        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month'))
            ? CarbonImmutable::createFromFormat('Y-m-d', $request->query('month').'-01', $tz)->startOfDay()
            : $today->startOfMonth();

        $gridStart = $month->startOfMonth()->startOfWeek(CarbonImmutable::MONDAY);
        $gridEnd = $month->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);

        // The selected day: ?day= when it is a real date inside the grid, else today (when in this month) or the 1st.
        $selected = null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('day'))) {
            try {
                $candidate = CarbonImmutable::createFromFormat('Y-m-d', $request->query('day'), $tz)->startOfDay();
                $selected = $candidate->between($gridStart, $gridEnd) ? $candidate : null;
            } catch (\Throwable) {
                $selected = null;
            }
        }
        $selected ??= $today->isSameMonth($month) ? $today : $month->startOfMonth();

        // Tickets and follow-ups are plain dates; plans are UTC instants shown in the viewer's clock.
        $from = $gridStart->format('Y-m-d').' 00:00:00';
        $to = $gridEnd->format('Y-m-d').' 23:59:59';

        $tickets = Ticket::open()->whereNotNull('tickets.due_at')->whereBetween('tickets.due_at', [$from, $to])
            ->with('company')->orderBy('tickets.due_at')->get()
            ->groupBy(fn ($t) => $t->due_at->format('Y-m-d'));

        $followUps = FollowUp::with('contact.company')
            ->where('assigned_to', $user->user_id)->where('status', 'pending')
            ->whereBetween('due_date', [$gridStart->format('Y-m-d'), $gridEnd->format('Y-m-d')])
            ->orderBy('due_date')->get()
            ->groupBy(fn ($f) => $f->due_date->format('Y-m-d'));

        $events = CalendarEvent::where('user_id', $user->user_id)
            ->whereBetween('start_at', [$gridStart->utc()->format('Y-m-d H:i:s'), $gridEnd->utc()->format('Y-m-d H:i:s')])
            ->orderBy('start_at')->get()
            ->groupBy(fn ($e) => UserTime::local($e->start_at, $tz)->format('Y-m-d'));

        $overdueQuery = fn () => Ticket::open()->where('tickets.due_at', '<', now());

        return view('calendar.index', [
            'tz' => $tz,
            'today' => $today,
            'month' => $month,
            'selected' => $selected,
            'gridStart' => $gridStart,
            'gridEnd' => $gridEnd,
            'tickets' => $tickets,
            'followUps' => $followUps,
            'events' => $events,
            'overdue' => $overdueQuery()->with('company')->orderBy('tickets.due_at')->limit(8)->get(),
            'overdueTotal' => $overdueQuery()->count(),
            'dueTodayCount' => Ticket::open()->whereDate('tickets.due_at', $today->format('Y-m-d'))->where('tickets.due_at', '>=', now())->count(),
            'plansToday' => ($events[$today->format('Y-m-d')] ?? collect())->where('status', 'planned')->count(),
            'followUpsDue' => FollowUp::where('assigned_to', $user->user_id)->where('status', 'pending')->whereDate('due_date', '<=', $today->format('Y-m-d'))->count(),
            'reminders' => CalendarEvent::REMINDERS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $local] = $this->validated($request);

        CalendarEvent::create($data + ['user_id' => $request->user()->user_id, 'status' => 'planned']);

        return $this->backToDay($local)->with('status', 'Added to your calendar.');
    }

    public function update(Request $request, CalendarEvent $event): RedirectResponse
    {
        $this->own($request, $event);
        [$data, $local] = $this->validated($request);

        // Moving the plan or changing its reminder re-arms the reminder; fixing a typo in the title must not send it again.
        $rescheduled = ! $event->start_at->equalTo($data['start_at']) || $event->remind_minutes !== $data['remind_minutes'];
        if (! $rescheduled) {
            unset($data['reminded_at']);
        }

        $event->update($data);

        return $this->backToDay($local)->with('status', 'Plan updated.');
    }

    /** Tick a plan off (or put it back). A plan that is done never sends its reminder. */
    public function toggleDone(Request $request, CalendarEvent $event): RedirectResponse
    {
        $this->own($request, $event);

        $event->update(['status' => $event->isDone() ? 'planned' : 'done']);

        return back()->with('status', $event->isDone() ? 'Marked done.' : 'Back on your plan.');
    }

    public function destroy(Request $request, CalendarEvent $event): RedirectResponse
    {
        $this->own($request, $event);
        $local = UserTime::local($event->start_at, UserTime::zone($request));

        $event->delete();

        return $this->backToDay($local)->with('status', 'Removed from your calendar.');
    }

    // ---- helpers ----------------------------------------------------------

    /** A user can only reach their own plans; anyone else's looks like it does not exist. */
    private function own(Request $request, CalendarEvent $event): void
    {
        abort_unless($event->user_id === $request->user()->user_id, 404);
    }

    private function backToDay(CarbonImmutable $local): RedirectResponse
    {
        return redirect()->route('calendar.index', ['month' => $local->format('Y-m'), 'day' => $local->format('Y-m-d')]);
    }

    /** @return array{0: array<string, mixed>, 1: CarbonImmutable} the columns to save and the start in the user's clock */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['nullable', 'date_format:H:i'],
            'all_day' => ['nullable', 'boolean'],
            'remind_minutes' => ['nullable', 'integer', Rule::in(CalendarEvent::REMINDERS)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $tz = UserTime::zone($request);
        $allDay = $request->boolean('all_day');
        $start = UserTime::toUtc($v['date'], $allDay ? self::ALL_DAY_REMINDER_HOUR : ($v['time'] ?? self::ALL_DAY_REMINDER_HOUR), $tz);

        return [[
            'title' => trim($v['title']),
            'notes' => filled($v['notes'] ?? null) ? trim($v['notes']) : null,
            'start_at' => $start,
            'all_day' => $allDay,
            'remind_minutes' => isset($v['remind_minutes']) && $v['remind_minutes'] !== '' ? (int) $v['remind_minutes'] : null,
            // Scheduling (or moving) a plan that is already in the past must not fire a reminder straight away.
            'reminded_at' => $start->lte(now()) ? now()->utc() : null,
        ], $start->setTimezone($tz)];
    }
}
