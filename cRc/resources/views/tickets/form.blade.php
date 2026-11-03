@extends('layouts.app')
@php($editing = $ticket->exists)
@php($isAdmin = auth()->user()->isAdmin())
@section('title', $editing ? 'Edit ticket' : 'New ticket')

@section('content')
<div class="card f" style="max-width:600px">
    <h2 style="margin-top:0">{{ $editing ? 'Edit '.$ticket->ticket_no : 'New ticket' }}</h2>
    <form method="POST" action="{{ $editing ? route('tickets.update', $ticket) : route('tickets.store') }}">
        @csrf
        @if($editing) @method('PUT') @endif

        @if($editing)
            <p class="muted">{{ $ticket->contact->company->name }} · {{ $ticket->contact->full_name }}</p>
        @else
            <label>Raised by (contact) *</label>
            <select name="contact_id" required>
                <option value="">Select a contact</option>
                @foreach($contactsByCompany as $companyName => $contacts)
                    <optgroup label="{{ $companyName }}">
                        @foreach($contacts as $c)
                            <option value="{{ $c->contact_id }}" @selected(old('contact_id', $ticket->contact_id) == $c->contact_id)>{{ $c->full_name }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            @error('contact_id')<div class="error">{{ $message }}</div>@enderror
        @endif

        <label>Title *</label>
        <input name="title" value="{{ old('title', $ticket->title) }}" required>
        @error('title')<div class="error">{{ $message }}</div>@enderror

        <label>Description</label>
        <textarea name="description" rows="5" style="width:100%;padding:.5rem;border:1px solid #cbd2d9;border-radius:6px;margin:.3rem 0 .6rem">{{ old('description', $ticket->description) }}</textarea>

        <label>Type</label>
        <select name="topic">
            @foreach(\App\Models\Ticket::TOPICS as $t)
                <option value="{{ $t }}" @selected(old('topic', $ticket->topic) === $t)>{{ $t }}</option>
            @endforeach
        </select>

        <label>Priority</label>
        <select name="priority">
            @foreach(array_reverse(\App\Models\Ticket::PRIORITIES) as $p)
                <option value="{{ $p }}" @selected(old('priority', $ticket->priority) === $p)>{{ $p }}</option>
            @endforeach
        </select>

        @if($editing)
            <label>Status</label>
            <select name="status">
                @foreach(\App\Models\Ticket::STATUSES as $s)
                    <option value="{{ $s }}" @selected(old('status', $ticket->status) === $s)>{{ str_replace('_', ' ', $s) }}</option>
                @endforeach
            </select>
        @endif

        <label>Due date</label>
        <input name="due_at" type="datetime-local" value="{{ old('due_at', $ticket->due_at?->format('Y-m-d\TH:i')) }}">
        @error('due_at')<div class="error">{{ $message }}</div>@enderror

        @if(! $editing && $isAdmin)
            <label>Assign to</label>
            <select name="assigned_to">
                <option value="">Unassigned</option>
                @foreach($agents as $a)
                    <option value="{{ $a->user_id }}" @selected(old('assigned_to') == $a->user_id)>{{ $a->full_name }}</option>
                @endforeach
            </select>
            @error('assigned_to')<div class="error">{{ $message }}</div>@enderror
        @elseif(! $editing)
            <p class="muted">As an agent, this ticket will be assigned to you.</p>
        @endif

        <button class="btn" type="submit">{{ $editing ? 'Save changes' : 'Create ticket' }}</button>
        <a href="{{ $editing ? route('tickets.show', $ticket) : route('tickets.index') }}" style="margin-left:1rem">Cancel</a>
    </form>
</div>
@endsection
