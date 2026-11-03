@extends('layouts.app')
@section('title', 'Admin dashboard')

@php($colors = ['critical' => '#d94b4b', 'high' => '#f0a23b', 'medium' => '#5b8def', 'low' => '#b5bcc6'])

@section('content')
@if(session('status'))<div class="flash">{{ session('status') }}</div>@endif
@error('assigned_to')<div class="error" style="margin-bottom:.6rem">{{ $message }}</div>@enderror

<h2 style="margin:0 0 .2rem">Admin dashboard</h2>
<p class="muted" style="margin:0 0 1rem">Welcome, {{ auth()->user()->full_name }}. Assign the queue and keep the team's load balanced.</p>

<div class="stat-grid">
    <a class="stat" href="{{ route('tickets.index') }}"><span class="stat-n">{{ $cards['open'] }}</span><span class="muted">Open tickets</span></a>
    <a class="stat {{ $cards['unassigned'] > 0 ? 'warn' : '' }}" href="{{ route('tickets.index', ['assigned' => 'unassigned']) }}"><span class="stat-n">{{ $cards['unassigned'] }}</span><span class="muted">Unassigned</span></a>
    <a class="stat {{ $cards['critical'] > 0 ? 'bad' : '' }}" href="{{ route('tickets.index', ['priority' => 'critical']) }}"><span class="stat-n">{{ $cards['critical'] }}</span><span class="muted">Critical open</span></a>
    <a class="stat {{ $cards['overdue'] > 0 ? 'bad' : '' }}" href="{{ route('tickets.index', ['overdue' => 1]) }}"><span class="stat-n">{{ $cards['overdue'] }}</span><span class="muted">Overdue</span></a>
    <a class="stat" href="{{ route('interactions.index', ['from' => today()->toDateString()]) }}"><span class="stat-n">{{ $cards['conversations_today'] }}</span><span class="muted">Conversations today</span></a>
    <div class="stat {{ $cards['followups_overdue'] > 0 ? 'warn' : '' }}"><span class="stat-n">{{ $cards['followups_overdue'] }}</span><span class="muted">Follow-ups overdue</span></div>
</div>

<div class="card" style="margin-top:1rem">
    <div class="hist-top" style="align-items:baseline">
        <h3 style="margin:0">Unassigned queue</h3>
        <span class="muted">{{ $queueTotal }} {{ \Illuminate\Support\Str::plural('ticket', $queueTotal) }} waiting</span>
    </div>
    <table class="list">
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
                        <select name="assigned_to" required aria-label="Assign {{ $t->ticket_no }} to" style="padding:.35rem;border:1px solid #cbd2d9;border-radius:6px">
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

<div class="card" style="margin-top:1rem">
    <div class="hist-top" style="align-items:baseline">
        <h3 style="margin:0">Workload per agent</h3>
        <span class="muted" style="font-size:.85rem">
            @foreach($colors as $p => $c)<span style="display:inline-block;width:.7rem;height:.7rem;background:{{ $c }};border-radius:2px;margin-left:.6rem"></span> {{ $p }}@endforeach
        </span>
    </div>
    <table class="list">
        <tr><th>Agent</th><th style="width:40%">Open tickets by priority</th><th>Total</th><th>Overdue</th><th></th></tr>
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

<p style="margin-top:1rem">
    <a class="btn outline" href="{{ route('tickets.index') }}">All tickets</a>
    <a class="btn outline" href="{{ route('admin.users.index') }}">Users</a>
    <a class="btn outline" href="{{ route('templates.index') }}">Templates</a>
    <a class="btn outline" href="{{ route('admin.modules.index') }}">Modules</a>
</p>
@endsection
