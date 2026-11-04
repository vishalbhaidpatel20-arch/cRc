@extends('layouts.app')
@section('title', 'Follow-ups')
@php($isAdmin = auth()->user()->isAdmin())

@push('assets')
@include('partials.grid-assets')
@endpush

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <h2 style="margin:0">Follow-ups</h2>
    </div>
    <p class="muted" style="margin:.3rem 0 0">
        @if($isAdmin)
            The calls and messages the team promised after a conversation. Overdue ones are at the top of the list.
        @else
            The calls and messages you promised after a conversation. Mark each one done when you have followed up.
        @endif
    </p>
    @include('partials.flash')

    <form method="GET" class="filters">
        <input name="q" value="{{ request('q') }}" placeholder="Search" title="Search contact, company or what was promised" autocomplete="off">
        <select name="filter" aria-label="Show">
            @foreach($filters as $key => $label)
                <option value="{{ $key }}" @selected($filter === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @if($isAdmin)
            <select name="agent" aria-label="Agent">
                <option value="">All agents</option>
                @foreach($agents as $a)
                    <option value="{{ $a->user_id }}" @selected(request('agent') == $a->user_id)>{{ $a->full_name }}</option>
                @endforeach
            </select>
        @endif
        <noscript><button class="btn" type="submit">Filter</button></noscript>
    </form>

<div data-region="results">
    <x-grid-skeleton :total="$followUps->count()" />
    <table class="list" data-grid>
        <tr><th>Contact</th><th>Company</th><th data-flex="3">Follow up on</th><th>Due</th>@if($isAdmin)<th>Agent</th>@endif<th>Status</th><th data-nosort></th></tr>
        @forelse($followUps as $f)
            <tr id="follow-up-{{ $f->follow_up_id }}">
                <td><a href="{{ route('contacts.show', $f->contact) }}">{{ $f->contact->full_name }}</a></td>
                <td class="muted">{{ $f->contact->company->name }}</td>
                <td>{{ $f->description }}</td>
                <td>
                    @if($f->isOverdue())<span class="pill late">Overdue</span> <span class="muted">{{ $f->due_date->format('j M') }}</span>
                    @elseif($f->status === 'pending')<span class="pill fu">{{ ucfirst($f->dueLabel()) }}</span>
                    @else<span class="muted">{{ $f->due_date->format('j M') }}</span>@endif
                </td>
                @if($isAdmin)<td>{{ $f->assignee->full_name }}</td>@endif
                <td>
                    @if($f->status === 'done')<span class="pill done">Done</span>
                    @elseif($f->status === 'cancelled')<span class="pill">Cancelled</span>
                    @else<span class="pill fu">Pending</span>@endif
                </td>
                <td style="text-align:right">
                    @if($f->status === 'pending')
                        <form method="POST" action="{{ route('follow-ups.done', $f) }}">
                            @csrf @method('PATCH')
                            <button class="btn outline" type="submit">Mark done</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="{{ $isAdmin ? 7 : 6 }}" class="muted">No follow-ups found.</td></tr>
        @endforelse
    </table>
    {{ $followUps->links('partials.pager') }}
</div>
</div>
@endsection
