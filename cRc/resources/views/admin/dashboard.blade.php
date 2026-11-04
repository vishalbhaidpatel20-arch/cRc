@extends('layouts.app')
@section('title', 'Admin dashboard')

@php($colors = ['critical' => '#d94b4b', 'high' => '#f0a23b', 'medium' => '#5b8def', 'low' => '#b5bcc6'])
@php($statusColors = ['open' => '#5B4BDB', 'in_progress' => '#2b9fd8', 'waiting' => '#f0a23b'])

@push('assets')
@include('partials.grid-assets')
@endpush

@section('content')
@include('partials.flash')

<div class="dash-hero">
    <div>
        <div class="dash-eyebrow" data-greeting>Dashboard</div>
        <script>(function (el) { var h = new Date().getHours(); el.textContent = (h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening') + ' · ' + new Date().toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long' }); })(document.currentScript.previousElementSibling);</script>
        <h2>{{ \Illuminate\Support\Str::of(auth()->user()->full_name)->before(' ') }}'s dashboard</h2>
        <p class="dash-sub">Welcome, {{ auth()->user()->full_name }}. Assign the queue and keep the team's load balanced.</p>
    </div>
    <div class="dash-actions">
        <a class="btn" href="{{ route('tickets.index') }}">All tickets</a>
        <a class="btn outline" href="{{ route('admin.users.index') }}">Users</a>
        <a class="btn outline" href="{{ route('templates.index') }}">Templates</a>
        <a class="btn outline" href="{{ route('admin.modules.index') }}">Modules</a>
    </div>
</div>

<div class="dash-stats" data-region="cards">
    <x-stat icon="ticket" tone="brand" :value="$cards['open']" label="Open tickets" hint="not yet resolved" :href="route('tickets.index')" />
    <x-stat icon="inbox" :tone="$cards['unassigned'] > 0 ? 'warn' : 'good'" :value="$cards['unassigned']" label="Unassigned" :hint="$cards['unassigned'] > 0 ? 'need an owner' : 'all have an owner'" :href="route('tickets.index', ['assigned' => 'unassigned'])" />
    <x-stat icon="alert" :tone="$cards['critical'] > 0 ? 'bad' : 'good'" :value="$cards['critical']" label="Critical open" hint="highest priority" :href="route('tickets.index', ['priority' => 'critical'])" />
    <x-stat icon="clock" :tone="$cards['overdue'] > 0 ? 'bad' : 'good'" :value="$cards['overdue']" label="Overdue" hint="past the due date" :href="route('tickets.index', ['overdue' => 1])" />
    <x-stat icon="chat" tone="info" :value="$cards['conversations_today']" label="Conversations today" hint="logged by the team" :href="route('interactions.index', ['from' => today()->toDateString()])" />
    <x-stat icon="bell" :tone="$cards['followups_overdue'] > 0 ? 'warn' : 'good'" :value="$cards['followups_overdue']" label="Follow-ups overdue" hint="promised calls missed" />
</div>

<div class="dash-grid">
    <div class="panel" data-region="queue">
        @error('assigned_to')<div class="error" style="margin-bottom:.6rem">{{ $message }}</div>@enderror
        <div class="panel-head">
            <div>
                <h3 class="panel-title">Unassigned queue</h3>
                <div class="panel-sub">Most urgent first, then oldest</div>
            </div>
            <span class="count-chip {{ $queueTotal > 0 ? 'warn' : 'good' }}">{{ $queueTotal }} {{ \Illuminate\Support\Str::plural('ticket', $queueTotal) }} waiting</span>
        </div>
        <x-grid-skeleton :total="$queue->count()" :page-size="5" />
        <table class="list" data-grid data-page-size="5">
            <tr><th>Ticket</th><th data-flex="2.6">Title</th><th>Priority</th><th>Due</th><th data-nosort data-flex="3.2">Assign</th></tr>
            @forelse($queue as $t)
                <tr>
                    <td style="white-space:nowrap"><a href="{{ route('tickets.show', $t) }}">{{ $t->ticket_no }}</a></td>
                    <td>{{ $t->title }}<div class="muted" style="font-size:.85rem">{{ $t->company->name }} &middot; {{ $t->contact->full_name }}</div></td>
                    <td><span class="tag {{ $t->priority }}">{{ $t->priority }}</span></td>
                    <td class="{{ $t->isOverdue() ? 'overdue' : 'muted' }}" style="white-space:nowrap">{{ $t->due_at ? 'Due '.$t->due_at->format('j M') : $t->created_at->diffForHumans(null, true) }}</td>
                    <td style="text-align:right">
                        <form method="POST" action="{{ route('tickets.assign', $t) }}" style="display:flex;gap:.4rem;justify-content:flex-end">
                            @csrf
                            <input type="hidden" name="from" value="dashboard">
                            <select name="assigned_to" required aria-label="Assign {{ $t->ticket_no }} to" style="padding:.35rem;border:1px solid #cbd2d9;border-radius:6px;min-width:0;flex:1 1 150px;max-width:220px">
                                <option value="">Assign to...</option>
                                @foreach($assignable as $a)
                                    <option value="{{ $a->user_id }}">{{ $a->full_name }} ({{ $a->open_total }} open)</option>
                                @endforeach
                            </select>
                            <button class="btn outline" type="submit">Assign</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td class="muted">The queue is empty. Every open ticket has an owner.</td></tr>
            @endforelse
        </table>
        @if($queueTotal > $queue->count())
            <p style="margin:.8rem 0 0"><a href="{{ route('tickets.index', ['assigned' => 'unassigned']) }}">See all {{ $queueTotal }} unassigned tickets</a></p>
        @endif
    </div>

    <div class="dash-side">
        <div class="panel">
            <div class="panel-head">
                <div>
                    <h3 class="panel-title">Open tickets by priority</h3>
                    <div class="panel-sub">{{ $cards['open'] }} open in total</div>
                </div>
            </div>
            @include('partials.dash-bars', ['empty' => 'No open tickets right now.', 'rows' => collect($colors)->map(fn ($c, $p) => [
                'label' => ucfirst($p), 'value' => (int) ($byPriority[$p] ?? 0), 'color' => $c, 'href' => route('tickets.index', ['priority' => $p]),
            ])->values()->all()])
        </div>

        <div class="panel">
            <div class="panel-head">
                <div>
                    <h3 class="panel-title">Open tickets by status</h3>
                    <div class="panel-sub">Where the work is right now</div>
                </div>
            </div>
            @include('partials.dash-bars', ['empty' => 'No open tickets right now.', 'rows' => collect($statusColors)->map(fn ($c, $s) => [
                'label' => ucfirst(str_replace('_', ' ', $s)), 'value' => (int) ($byStatus[$s] ?? 0), 'color' => $c, 'href' => route('tickets.index', ['status' => $s]),
            ])->values()->all()])
        </div>
    </div>
</div>

<div class="dash-grid">
    <div class="panel" data-region="workload">
        <div class="panel-head">
            <div>
                <h3 class="panel-title">Workload per agent</h3>
                <div class="panel-sub">Open tickets by priority, busiest first</div>
            </div>
            <span class="legend">
                @foreach($colors as $p => $c)<span class="legend-dot" style="background:{{ $c }}"></span> {{ $p }}@endforeach
            </span>
        </div>
        <x-grid-skeleton :total="$workload->count()" :page-size="8" />
        <table class="list" data-grid data-page-size="8">
            <tr><th>Agent</th><th data-flex="3" data-nosort>Open tickets by priority</th><th>Total</th><th>Overdue</th><th></th></tr>
            @forelse($workload as $a)
                <tr>
                    <td>{{ $a->full_name }} @unless($a->is_active)<span class="pill late">Deactivated</span>@endunless</td>
                    <td>
                        @if($a->open_total > 0)
                            <div class="bar" style="width:{{ round($a->open_total / $maxOpen * 100) }}%" title="{{ $a->critical }} critical, {{ $a->high }} high, {{ $a->medium }} medium, {{ $a->low }} low">
                                @foreach($colors as $p => $c)@if($a->{$p} > 0)<span style="flex:{{ $a->{$p} }};background:{{ $c }}"></span>@endif @endforeach
                            </div>
                        @else
                            <span class="muted">No open tickets</span>
                        @endif
                    </td>
                    <td><strong>{{ $a->open_total }}</strong></td>
                    <td class="{{ $a->overdue > 0 ? 'overdue' : 'muted' }}">{{ $a->overdue }}</td>
                    <td style="text-align:right"><a href="{{ route('tickets.index', ['assigned' => $a->user_id]) }}">View tickets</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No agents yet. Create one under Users.</td></tr>
            @endforelse
        </table>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div>
                <h3 class="panel-title">Recent conversations</h3>
                <div class="panel-sub">The latest five across the team</div>
            </div>
            <a href="{{ route('interactions.index') }}" style="font-size:.85rem">View all</a>
        </div>
        @include('partials.conversation-list', ['interactions' => $recent, 'showContact' => true, 'showTicket' => true])
    </div>
</div>
@endsection
