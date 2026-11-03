@extends('layouts.app')
@section('title', 'Agent dashboard')

@section('content')
<div class="card">
    <h2 style="margin-top:0">Agent dashboard</h2>
    <p>Welcome, {{ auth()->user()->full_name }}. You have <strong>{{ $openCount }}</strong> open {{ Str::plural('ticket', $openCount) }}.</p>

    <p>
        @foreach(['critical', 'high', 'medium', 'low'] as $p)
            <span class="tag {{ $p }}">{{ $p }}: {{ $byPriority[$p] ?? 0 }}</span>
        @endforeach
    </p>
    <p><a class="btn" href="{{ route('tickets.index') }}">My tickets</a>
       <a class="btn light" href="{{ route('tickets.create') }}">+ New ticket</a></p>

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
    <table class="list">
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
