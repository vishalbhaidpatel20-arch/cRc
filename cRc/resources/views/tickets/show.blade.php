@extends('layouts.app')
@php($isAdmin = auth()->user()->isAdmin())
@section('title', $ticket->ticket_no)

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <h2 style="margin:0">{{ $ticket->ticket_no }}: {{ $ticket->title }}</h2>
        <a class="btn light" id="edit-ticket-link" data-modal="ticket-edit-modal" href="{{ route('tickets.edit', $ticket) }}">Edit</a>
    </div>
    @if(session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <p>
        <span class="tag {{ $ticket->priority }}">{{ $ticket->priority }}</span>
        <span class="tag">{{ str_replace('_', ' ', $ticket->status) }}</span>
        <span class="tag">{{ $ticket->topic }}</span>
    </p>

    <table class="list" style="max-width:640px">
        <tr><th>Company</th><td><a href="{{ route('companies.show', $ticket->company) }}">{{ $ticket->company->name }}</a></td></tr>
        <tr><th>Raised by</th><td><a href="{{ route('contacts.show', $ticket->contact) }}">{{ $ticket->contact->full_name }}</a>
            <span class="muted">{{ $ticket->contact->phone }} {{ $ticket->contact->email }}</span></td></tr>
        <tr><th>Assigned to</th><td>{!! $ticket->assignee ? e($ticket->assignee->full_name) : '<span class="muted">Unassigned</span>' !!}</td></tr>
        <tr><th>Due</th><td class="{{ $ticket->isOverdue() ? 'overdue' : '' }}">{{ $ticket->due_at?->format('d M Y H:i') ?? '-' }}</td></tr>
        <tr><th>Created</th><td>{{ $ticket->created_at?->format('d M Y H:i') }}</td></tr>
    </table>

    @if($ticket->description)
        <h3>Description</h3>
        <p style="white-space:pre-wrap">{{ $ticket->description }}</p>
    @endif

    @if($isAdmin)
        <h3>{{ $ticket->assigned_to ? 'Reassign' : 'Assign' }}</h3>
        <form method="POST" action="{{ route('tickets.assign', $ticket) }}" class="filters" style="margin-top:0">
            @csrf
            <select name="assigned_to" required>
                <option value="">Select an agent</option>
                @foreach($agents as $a)
                    <option value="{{ $a->user_id }}" @selected(old('assigned_to') == $a->user_id)>{{ $a->full_name }}</option>
                @endforeach
            </select>
            <input name="reason" value="{{ old('reason') }}" placeholder="Reason (optional)" style="flex:1;min-width:200px">
            <button class="btn" type="submit">{{ $ticket->assigned_to ? 'Reassign' : 'Assign' }}</button>
        </form>
        @error('assigned_to')<div class="error">{{ $message }}</div>@enderror
    @endif

    <h3>Assignment history</h3>
    <table class="list">
        <tr><th>When</th><th>From</th><th>To</th><th>By</th><th>Reason</th></tr>
        @forelse($history as $h)
            <tr>
                <td>{{ $h->assigned_at->format('d M Y H:i') }}</td>
                <td>{{ $h->fromUser?->full_name ?? '-' }}</td>
                <td>{{ $h->toUser?->full_name ?? '-' }}</td>
                <td>{{ $h->assignedBy?->full_name }}</td>
                <td class="muted">{{ $h->reason }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">Never assigned.</td></tr>
        @endforelse
    </table>

    @include('partials.conversation-panel', [
        'action' => route('tickets.interactions.store', $ticket),
        'anchorUrl' => route('tickets.show', $ticket).'#log-conversation',
        'contactChoices' => $panelContacts,
        'defaultContactId' => $ticket->contact_id,
        'defaultTopic' => $ticket->topic,
    ])

    <div class="hist-head" id="conversations">
        <h3>Conversation history</h3>
        <span class="muted">{{ $interactions->count() }} {{ \Illuminate\Support\Str::plural('conversation', $interactions->count()) }}</span>
    </div>
    @include('partials.conversation-list', ['interactions' => $interactions, 'showContact' => true])
</div>

<x-modal id="ticket-edit-modal" marker="ticket-edit" :title="'Edit '.$ticket->ticket_no" :action="route('tickets.update', $ticket)" method="PUT" submit="Save changes" wide>
    @include('tickets._fields', ['editing' => true])
</x-modal>
@endsection
