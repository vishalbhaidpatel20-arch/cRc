@extends('layouts.app')
@section('title', 'Notifications')

@push('assets')
@include('partials.grid-assets')
@endpush

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <h2 style="margin:0">Notifications</h2>
        <form method="POST" action="{{ route('notifications.read') }}">
            @csrf
            <button class="btn outline" type="submit">Mark all as read</button>
        </form>
    </div>
    <p class="muted" style="margin:.3rem 0 0">
        @if(auth()->user()->isAdmin())
            You are told here when an agent completes a ticket you assigned.
        @else
            You are told here when an admin assigns you a ticket.
        @endif
    </p>

<div data-region="results">
    <x-grid-skeleton :total="$notifications->count()" />
    <table class="list" data-grid>
        <tr><th>Status</th><th>Type</th><th data-flex="4">Notification</th><th>When</th></tr>
        @php($visibleTickets = \App\Support\NotificationPresenter::visibleTicketIds($notifications))
        @forelse($notifications as $n)
            @php($p = \App\Support\NotificationPresenter::present($n, isset($visibleTickets[$n->data['ticket_id'] ?? 0])))
            <tr id="notification-{{ $n->id }}">
                <td>@if($n->read_at)<span class="pill done">Read</span>@else<span class="pill green">New</span>@endif</td>
                <td><span class="bell-tag {{ $p['kind'] }}">{{ $p['tag'] }}</span></td>
                <td><a href="{{ route('notifications.open', $n->id) }}">{{ $p['text'] }}</a>@if($p['detail']) <span class="muted">({{ $p['detail'] }})</span>@endif</td>
                <td class="muted" title="{{ $n->created_at->format('d M Y H:i') }}">{{ $n->created_at->diffForHumans() }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">No notifications yet.</td></tr>
        @endforelse
    </table>
    {{ $notifications->links('partials.pager') }}
</div>
</div>
@endsection
