@extends('layouts.app')
@php($isAdmin = auth()->user()->isAdmin())
@section('title', $isAdmin ? 'Tickets' : 'My tickets')

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <h2 style="margin:0">{{ $isAdmin ? 'Tickets' : 'My tickets' }}</h2>
        <a class="btn" href="{{ route('tickets.create') }}">+ New ticket</a>
    </div>
    @if(session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <form method="GET" class="filters">
        <input name="q" value="{{ request('q') }}" placeholder="Search ticket no, title, description" style="flex:1;min-width:220px">
        <select name="status">
            <option value="open" @selected($status === 'open')>Not resolved</option>
            @foreach(\App\Models\Ticket::STATUSES as $s)
                <option value="{{ $s }}" @selected($status === $s)>{{ str_replace('_', ' ', ucfirst($s)) }}</option>
            @endforeach
            <option value="all" @selected($status === 'all')>All</option>
        </select>
        <select name="priority">
            <option value="">Any priority</option>
            @foreach(array_reverse(\App\Models\Ticket::PRIORITIES) as $p)
                <option value="{{ $p }}" @selected(request('priority') === $p)>{{ ucfirst($p) }}</option>
            @endforeach
        </select>
        <select name="company_id">
            <option value="">All companies</option>
            @foreach($companies as $c)
                <option value="{{ $c->company_id }}" @selected(request('company_id') == $c->company_id)>{{ $c->name }}</option>
            @endforeach
        </select>
        @if($isAdmin)
            <select name="assigned">
                <option value="">Anyone</option>
                <option value="unassigned" @selected(request('assigned') === 'unassigned')>Unassigned</option>
                @foreach($agents as $a)
                    <option value="{{ $a->user_id }}" @selected(request('assigned') == $a->user_id)>{{ $a->full_name }}</option>
                @endforeach
            </select>
        @endif
        <button class="btn" type="submit">Filter</button>
    </form>

    <table class="list">
        <tr><th>No</th><th>Title</th><th>Company / contact</th><th>Type</th><th>Priority</th><th>Status</th>
            @if($isAdmin)<th>Assigned to</th>@endif<th>Due</th></tr>
        @forelse($tickets as $t)
            <tr>
                <td><a href="{{ route('tickets.show', $t) }}">{{ $t->ticket_no }}</a></td>
                <td>{{ $t->title }}</td>
                <td>{{ $t->company->name }}<br><span class="muted">{{ $t->contact->full_name }}</span></td>
                <td>{{ $t->topic }}</td>
                <td><span class="tag {{ $t->priority }}">{{ $t->priority }}</span></td>
                <td>{{ str_replace('_', ' ', $t->status) }}</td>
                @if($isAdmin)<td>{!! $t->assignee ? e($t->assignee->full_name) : '<span class="muted">Unassigned</span>' !!}</td>@endif
                <td class="{{ $t->isOverdue() ? 'overdue' : 'muted' }}">{{ $t->due_at?->format('d M Y') ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ $isAdmin ? 8 : 7 }}" class="muted">No tickets found.</td></tr>
        @endforelse
    </table>
    <div style="margin-top:1rem">{{ $tickets->links('partials.pager') }}</div>
</div>
@endsection
