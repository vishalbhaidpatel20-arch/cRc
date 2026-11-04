@extends('layouts.app')
@section('title', 'Agent dashboard')

@push('assets')
@include('partials.grid-assets')
@endpush

@section('content')
<div class="card" data-region="board">
    <h2 style="margin-top:0">Agent dashboard</h2>
    <p>Welcome, {{ auth()->user()->full_name }}. You have <strong>{{ $openCount }}</strong> open {{ Str::plural('ticket', $openCount) }}.</p>

    <p>
        @foreach(['critical', 'high', 'medium', 'low'] as $p)
            <span class="tag {{ $p }}">{{ $p }}: {{ $byPriority[$p] ?? 0 }}</span>
        @endforeach
    </p>
    <p><a class="btn" href="{{ route('tickets.index') }}">My tickets</a>
       <a class="btn light" href="{{ route('tickets.index', ['new' => 1]) }}">+ New ticket</a></p>

    <h3>Follow-ups due</h3>
    @forelse($followUps as $f)
        <div style="padding:.5rem 0;border-bottom:1px solid #eee;display:flex;justify-content:space-between;gap:1rem;align-items:center">
            <div>
                <a href="{{ route('contacts.show', $f->contact) }}">{{ $f->contact->full_name }}</a>
                <span class="muted">{{ $f->contact->company->name }}</span>
                @if($f->isOverdue())<span class="pill late">Overdue, was {{ $f->due_date->format('j M') }}</span>@else<span class="pill fu">Due today</span>@endif
                <div class="muted">{{ $f->description }}</div>
            </div>
            <form method="POST" action="{{ route('follow-ups.done', $f) }}">
                @csrf @method('PATCH')
                <button class="btn outline" type="submit">Mark done</button>
            </form>
        </div>
    @empty
        <p class="muted">No follow-ups due.</p>
    @endforelse

    <h3>New assignments</h3>
    @forelse($notifications as $n)
        <div style="padding:.4rem 0;border-bottom:1px solid #eee">
            <a href="{{ route('tickets.show', $n->data['ticket_id']) }}">{{ $n->data['ticket_no'] }}</a>:
            {{ $n->data['title'] }}
            <span class="tag {{ $n->data['priority'] }}">{{ $n->data['priority'] }}</span>
            <span class="muted">assigned by {{ $n->data['assigned_by'] }}, {{ $n->created_at->diffForHumans() }}</span>
        </div>
    @empty
        <p class="muted">No new assignments.</p>
    @endforelse
    @if($notifications->isNotEmpty())
        <form method="POST" action="{{ route('notifications.read') }}" style="margin-top:.6rem">
            @csrf
            <button class="btn light" type="submit">Mark all as read</button>
        </form>
    @endif

    <h3>Next up</h3>
    <x-grid-skeleton :total="$topTickets->count()" :page-size="5" />
    <table class="list" data-grid data-page-size="5">
        <tr><th>No</th><th data-flex="3">Title</th><th>Company</th><th>Priority</th></tr>
        @forelse($topTickets as $t)
            <tr>
                <td><a href="{{ route('tickets.show', $t) }}">{{ $t->ticket_no }}</a></td>
                <td>{{ $t->title }}</td>
                <td class="muted">{{ $t->company->name }}</td>
                <td><span class="tag {{ $t->priority }}">{{ $t->priority }}</span></td>
            </tr>
        @empty
            <tr><td class="muted">Nothing open. Nice work.</td></tr>
        @endforelse
    </table>
</div>
@endsection
