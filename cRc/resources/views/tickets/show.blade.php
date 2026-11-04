@extends('layouts.app')
@php($isAdmin = auth()->user()->isAdmin())
@section('title', $ticket->ticket_no)

@push('assets')
@include('partials.grid-assets')
@endpush

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center" data-region="ticket-head">
        <h2 style="margin:0">{{ $ticket->ticket_no }}: {{ $ticket->title }}</h2>
        <a class="btn light" id="edit-ticket-link" data-modal="ticket-edit-modal" href="{{ route('tickets.edit', $ticket) }}">Edit</a>
    </div>
    @include('partials.flash')

    <div data-region="ticket">
    <p>
        <span class="tag {{ $ticket->priority }}">{{ $ticket->priority }}</span>
        <span class="tag">{{ str_replace('_', ' ', $ticket->status) }}</span>
        <span class="tag">{{ $ticket->topic }}</span>
    </p>

    {{-- The ticket's details as a small data grid (field / value), like every other table in the app. --}}
    <div style="max-width:820px">
        <x-grid-skeleton :total="5" :page-size="5" />
        <table class="list" data-grid data-page-size="5">
            <tr><th data-nosort>Detail</th><th data-nosort data-flex="4">Value</th></tr>
            <tr id="detail-company"><td class="muted">Company</td><td><a href="{{ route('companies.show', $ticket->company) }}">{{ $ticket->company->name }}</a></td></tr>
            <tr id="detail-contact"><td class="muted">Raised by</td><td><a href="{{ route('contacts.show', $ticket->contact) }}">{{ $ticket->contact->full_name }}</a>
                <span class="muted">{{ $ticket->contact->phone }} {{ $ticket->contact->email }}</span></td></tr>
            <tr id="detail-assignee"><td class="muted">Assigned to</td><td>{!! $ticket->assignee ? e($ticket->assignee->full_name) : '<span class="muted">Unassigned</span>' !!}</td></tr>
            <tr id="detail-due"><td class="muted">Due</td><td>@if($ticket->isOverdue())<span class="overdue">{{ $ticket->due_at?->format('d M Y H:i') }}</span>@else{{ $ticket->due_at?->format('d M Y H:i') ?? '-' }}@endif</td></tr>
            <tr id="detail-created"><td class="muted">Created</td><td>{{ $ticket->created_at?->format('d M Y H:i') }}</td></tr>
        </table>
    </div>

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
    <x-grid-skeleton :total="$history->count()" :page-size="5" />
    <table class="list" data-grid data-page-size="5">
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
    </div>

    <div data-region="log">
    @include('partials.conversation-panel', [
        'action' => route('tickets.interactions.store', $ticket),
        'anchorUrl' => route('tickets.show', $ticket).'#log-conversation',
        'contactChoices' => $panelContacts,
        'defaultContactId' => $ticket->contact_id,
        'defaultTopic' => $ticket->topic,
    ])
    </div>

    <div data-region="conversations">
    <div class="hist-head" id="conversations">
        <h3>Conversation history</h3>
        <span class="muted">{{ $interactions->count() }} {{ \Illuminate\Support\Str::plural('conversation', $interactions->count()) }}</span>
    </div>
    @include('partials.conversation-list', ['interactions' => $interactions, 'showContact' => true])
    </div>
</div>

<x-modal id="ticket-edit-modal" marker="ticket-edit" :title="'Edit '.$ticket->ticket_no" :action="route('tickets.update', $ticket)" method="PUT" submit="Save changes" wide>
    @include('tickets._fields', ['editing' => true])
</x-modal>
@endsection
