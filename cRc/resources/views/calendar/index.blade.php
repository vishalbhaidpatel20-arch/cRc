@extends('layouts.app')
@section('title', 'Calendar')

@php($sk = $selected->format('Y-m-d'))
@php($ym = $month->format('Y-m'))
@php($now = now())
@php($blank = new \App\Models\CalendarEvent)

@push('assets')
<style>
    .cal-nav{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:.9rem}
    .cal-nav h3{margin:0;font-size:1.25rem;letter-spacing:-.01em;color:var(--ink)}
    .cal-nav-btns{display:flex;gap:.4rem}
    .cal-nav-btns .btn{padding:.4rem .8rem;line-height:1.2}
    .cal-legend{display:flex;gap:1rem;flex-wrap:wrap;font-size:.78rem;color:#7b8794;margin-bottom:.8rem}
    .cal-legend i{display:inline-block;width:.7rem;height:.7rem;border-radius:3px;margin-right:.3rem;vertical-align:-1px}
    .cal-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));border-top:1px solid #e8eaf0;border-left:1px solid #e8eaf0;border-radius:10px;overflow:hidden}
    .cal-dow{padding:.5rem .6rem;font-size:.74rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#7b8794;background:#f8f9fb;border-right:1px solid #e8eaf0;border-bottom:1px solid #e8eaf0}
    .cal-day{display:flex;flex-direction:column;gap:3px;min-height:112px;padding:.4rem .45rem;border-right:1px solid #e8eaf0;border-bottom:1px solid #e8eaf0;color:inherit;text-decoration:none;background:#fff;min-width:0;transition:background .1s}
    .cal-day:hover{background:#faf9ff}
    .cal-day.out{background:#fafbfc;color:#aab2bd}
    .cal-day.selected{box-shadow:inset 0 0 0 2px var(--brand);background:#f6f5ff}
    .cal-num{align-self:flex-start;min-width:24px;height:24px;padding:0 5px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:600}
    .cal-day.today .cal-num{background:var(--brand);color:#fff}
    .cal-chip{display:block;font-size:.72rem;line-height:1.35;padding:1px 6px;border-radius:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .cal-chip.overdue{background:#fde8e8;color:#a11a1a;font-weight:600}
    .cal-chip.due{background:#fff1d6;color:#8a5200}
    .cal-chip.plan{background:#e8e4ff;color:#3c30a8}
    .cal-chip.fu{background:#dbeafe;color:#1d4e9e}
    .cal-chip.done{background:#eef0f3;color:#7b8794;text-decoration:line-through}
    .cal-more{font-size:.7rem;color:#7b8794;padding-left:4px}
    .ag-item{display:grid;grid-template-columns:4.6rem minmax(0,1fr) auto;gap:.2rem .8rem;align-items:start;padding:.8rem 0;border-bottom:1px solid #f0f0f3}
    .ag-item:last-of-type{border-bottom:0}
    .ag-time{font-size:.82rem;font-weight:600;color:#3e4c59;padding-top:2px}
    .ag-title{font-weight:600;overflow-wrap:anywhere}
    .ag-title.done{text-decoration:line-through;color:#7b8794}
    .ag-meta{font-size:.8rem;color:#7b8794;margin-top:.15rem;overflow-wrap:anywhere}
    .ag-acts{display:flex;gap:.3rem;flex-wrap:wrap;justify-content:flex-end}
    .ag-acts form{display:inline}
    .ag-acts .btn,.ag-acts button{padding:.25rem .6rem;font-size:.78rem}
    .kind{display:inline-block;font-size:.68rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;border-radius:999px;padding:.05rem .5rem;margin-right:.35rem}
    .kind.plan{background:#e8e4ff;color:#3c30a8}.kind.overdue{background:#fde8e8;color:#a11a1a}.kind.due{background:#fff1d6;color:#8a5200}.kind.fu{background:#dbeafe;color:#1d4e9e}
    .od-row{display:flex;justify-content:space-between;gap:1rem;align-items:center;padding:.65rem 0;border-bottom:1px solid #f0f0f3;text-decoration:none;color:inherit}
    .od-row:last-of-type{border-bottom:0}
    .od-row:hover{background:#fff8f8}
    @media (max-width:700px){.cal-day{min-height:70px}.cal-chip{font-size:.62rem}.ag-item{grid-template-columns:3.6rem minmax(0,1fr)}.ag-acts{grid-column:2;justify-content:flex-start}}
</style>
@endpush

@section('content')
@include('partials.flash')

<div class="dash-hero" data-server-tz="{{ $tz }}">
    <div>
        <div class="dash-eyebrow">Plan your work</div>
        <h2>Calendar</h2>
        <p class="dash-sub">Tickets by due date (overdue in red), your follow-ups and your own plans. Set a reminder and it pops up in the bell.</p>
    </div>
    <div class="dash-actions">
        <a class="btn" id="new-event-link" data-modal="event-modal" href="{{ route('calendar.index', ['month' => $ym, 'day' => $sk, 'new' => 1]) }}">+ Plan something</a>
    </div>
</div>

<div class="dash-stats" data-region="cal-stats">
    <x-stat icon="alert" :tone="$overdueTotal > 0 ? 'bad' : 'good'" :value="$overdueTotal" label="Overdue tickets" :hint="$overdueTotal > 0 ? 'need attention now' : 'nothing late'" :href="route('tickets.index', ['overdue' => 1])" />
    <x-stat icon="clock" :tone="$dueTodayCount > 0 ? 'warn' : 'good'" :value="$dueTodayCount" label="Due later today" hint="tickets" :href="route('calendar.index', ['month' => $today->format('Y-m'), 'day' => $today->format('Y-m-d')])" />
    <x-stat icon="check" tone="brand" :value="$plansToday" label="Your plans today" hint="still to do" :href="route('calendar.index', ['month' => $today->format('Y-m'), 'day' => $today->format('Y-m-d')])" />
    <x-stat icon="bell" :tone="$followUpsDue > 0 ? 'warn' : 'good'" :value="$followUpsDue" label="Follow-ups due" hint="today or overdue" :href="route('follow-ups.index', ['filter' => 'due'])" />
</div>

<div class="dash-grid">
    <div class="panel" data-region="cal-main">
        <div class="cal-nav">
            <h3>{{ $month->format('F Y') }}</h3>
            <div class="cal-nav-btns">
                <a class="btn outline" href="{{ route('calendar.index', ['month' => $month->subMonth()->format('Y-m')]) }}" aria-label="Previous month">&lsaquo;</a>
                <a class="btn outline" href="{{ route('calendar.index') }}">Today</a>
                <a class="btn outline" href="{{ route('calendar.index', ['month' => $month->addMonth()->format('Y-m')]) }}" aria-label="Next month">&rsaquo;</a>
            </div>
        </div>
        <div class="cal-legend">
            <span><i style="background:#f4b3b3"></i>Overdue ticket</span>
            <span><i style="background:#f7d58f"></i>Ticket due</span>
            <span><i style="background:#c9c1fb"></i>Your plan</span>
            <span><i style="background:#a8c8f5"></i>Follow-up</span>
        </div>

        <div class="cal-grid">
            @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dow)<div class="cal-dow">{{ $dow }}</div>@endforeach

            @for($d = $gridStart; $d->lte($gridEnd); $d = $d->addDay())
                @php($key = $d->format('Y-m-d'))
                @php($dayTickets = $tickets[$key] ?? collect())
                @php($chips = collect())
                @foreach($dayTickets->filter(fn ($t) => $t->due_at->lt($now)) as $t)@php($chips->push(['overdue', $t->ticket_no.' '.$t->title]))@endforeach
                @foreach(($events[$key] ?? collect()) as $e)@php($chips->push([$e->isDone() ? 'done' : 'plan', ($e->all_day ? '' : \App\Support\UserTime::local($e->start_at, $tz)->format('H:i').' ').$e->title]))@endforeach
                @foreach(($followUps[$key] ?? collect()) as $f)@php($chips->push(['fu', 'Call '.$f->contact->full_name]))@endforeach
                @foreach($dayTickets->filter(fn ($t) => $t->due_at->gte($now)) as $t)@php($chips->push(['due', $t->ticket_no.' '.$t->title]))@endforeach
                <a class="cal-day {{ $d->isSameMonth($month) ? '' : 'out' }} {{ $d->isSameDay($today) ? 'today' : '' }} {{ $key === $sk ? 'selected' : '' }}"
                   href="{{ route('calendar.index', ['month' => $ym, 'day' => $key]) }}" title="{{ $d->format('l, j F Y') }}">
                    <span class="cal-num">{{ $d->day }}</span>
                    @foreach($chips->take(3) as [$cls, $text])<span class="cal-chip {{ $cls }}">{{ $text }}</span>@endforeach
                    @if($chips->count() > 3)<span class="cal-more">+{{ $chips->count() - 3 }} more</span>@endif
                </a>
            @endfor
        </div>
    </div>

    <div class="dash-side">
        <div class="panel" data-region="cal-agenda">
            <div class="panel-head">
                <div>
                    <h3 class="panel-title">{{ $selected->isSameDay($today) ? 'Today' : $selected->format('l') }}, {{ $selected->format('j F') }}</h3>
                    <div class="panel-sub">Your day at a glance</div>
                </div>
                <a class="btn outline" style="padding:.3rem .7rem;font-size:.82rem" data-modal="event-modal" href="{{ route('calendar.index', ['month' => $ym, 'day' => $sk, 'new' => 1]) }}">+ Plan</a>
            </div>

            @php($dayEvents = ($events[$sk] ?? collect())->sortBy([fn ($a, $b) => (int) $b->all_day <=> (int) $a->all_day, fn ($a, $b) => $a->start_at <=> $b->start_at]))
            @php($dayDue = $tickets[$sk] ?? collect())
            @php($dayFollow = $followUps[$sk] ?? collect())

            @foreach($dayEvents as $e)
                @php($local = \App\Support\UserTime::local($e->start_at, $tz))
                <div class="ag-item">
                    <div class="ag-time">{{ $e->all_day ? 'All day' : $local->format('H:i') }}</div>
                    <div>
                        <div class="ag-title {{ $e->isDone() ? 'done' : '' }}"><span class="kind plan">Plan</span>{{ $e->title }}</div>
                        @if($e->notes)<div class="ag-meta" style="white-space:pre-wrap">{{ $e->notes }}</div>@endif
                        <div class="ag-meta">{{ \App\Models\CalendarEvent::reminderLabel($e->remind_minutes) }}@if($e->reminded_at && $e->remind_minutes !== null && ! $e->isDone()) &middot; reminder sent @endif</div>
                    </div>
                    <div class="ag-acts">
                        <form method="POST" action="{{ route('calendar.events.done', $e) }}">@csrf @method('PATCH')<button class="btn outline" type="submit">{{ $e->isDone() ? 'Reopen' : 'Done' }}</button></form>
                        <a class="btn outline" data-modal="event-edit-{{ $e->event_id }}" href="{{ route('calendar.index', ['month' => $ym, 'day' => $sk]) }}">Edit</a>
                        <form method="POST" action="{{ route('calendar.events.destroy', $e) }}">@csrf @method('DELETE')<button class="btn outline" type="submit">Delete</button></form>
                    </div>
                </div>
            @endforeach

            @foreach($dayDue as $t)
                @php($late = $t->due_at->lt($now))
                <div class="ag-item">
                    <div class="ag-time">{{ $t->due_at->format('H:i') }}</div>
                    <div>
                        <div class="ag-title"><span class="kind {{ $late ? 'overdue' : 'due' }}">{{ $late ? 'Overdue' : 'Due' }}</span><a href="{{ route('tickets.show', $t) }}">{{ $t->ticket_no }}</a> {{ $t->title }}</div>
                        <div class="ag-meta">{{ $t->company->name }} &middot; <span class="tag {{ $t->priority }}">{{ $t->priority }}</span></div>
                    </div>
                </div>
            @endforeach

            @foreach($dayFollow as $f)
                <div class="ag-item">
                    <div class="ag-time">Follow-up</div>
                    <div>
                        <div class="ag-title"><span class="kind fu">Call</span><a href="{{ route('contacts.show', $f->contact) }}">{{ $f->contact->full_name }}</a></div>
                        <div class="ag-meta">{{ $f->contact->company->name }} &middot; {{ $f->description }}</div>
                    </div>
                </div>
            @endforeach

            @if($dayEvents->isEmpty() && $dayDue->isEmpty() && $dayFollow->isEmpty())
                <p class="muted" style="margin:.9rem 0 0">Nothing on this day. Use <strong>+ Plan</strong> to schedule something.</p>
            @endif

            @foreach($dayEvents as $e)
                <x-modal :id="'event-edit-'.$e->event_id" :marker="'event-edit-'.$e->event_id" title="Edit plan" :action="route('calendar.events.update', $e)" method="PUT" submit="Save changes">
                    @include('calendar._fields', ['event' => $e, 'tz' => $tz, 'day' => $sk, 'reminders' => $reminders, 'scope' => 'event-edit-'.$e->event_id])
                </x-modal>
            @endforeach
        </div>

        <div class="panel" data-region="cal-overdue">
            <div class="panel-head">
                <div>
                    <h3 class="panel-title">Overdue now</h3>
                    <div class="panel-sub">Tickets past their due date</div>
                </div>
                @if($overdueTotal > 0)<span class="count-chip warn">{{ $overdueTotal }}</span>@endif
            </div>
            @forelse($overdue as $t)
                <a class="od-row" href="{{ route('tickets.show', $t) }}">
                    <span style="min-width:0">
                        <strong>{{ $t->ticket_no }}</strong> {{ $t->title }}
                        <span class="muted" style="display:block;font-size:.8rem">{{ $t->company->name }}</span>
                    </span>
                    <span class="overdue" style="white-space:nowrap;font-size:.8rem">{{ $t->due_at->diffForHumans(null, true) }} late</span>
                </a>
            @empty
                <p class="muted" style="margin:.9rem 0 0">No overdue tickets. Nice work.</p>
            @endforelse
            @if($overdueTotal > $overdue->count())
                <p style="margin:.8rem 0 0"><a href="{{ route('tickets.index', ['overdue' => 1]) }}">See all {{ $overdueTotal }} overdue tickets</a></p>
            @endif
        </div>
    </div>
</div>

<x-modal id="event-modal" marker="event-new" title="Plan something" :action="route('calendar.events.store')" submit="Add to calendar"
         note="Your plans are private to you. A reminder pops up in the bell at the time you choose." :open="request()->boolean('new')">
    @include('calendar._fields', ['event' => $blank, 'tz' => $tz, 'day' => $sk, 'reminders' => $reminders, 'scope' => 'event-new'])
</x-modal>
@endsection
