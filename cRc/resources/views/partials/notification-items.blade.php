{{-- The bell's dropdown rows. Expects $items (the user's latest notifications). Also returned by /notifications/feed. --}}
@php($visibleTickets = \App\Support\NotificationPresenter::visibleTicketIds($items))
@forelse($items as $n)
    @php($p = \App\Support\NotificationPresenter::present($n, isset($visibleTickets[$n->data['ticket_id'] ?? 0])))
    <a class="bell-item {{ $n->read_at ? '' : 'unread' }}" href="{{ route('notifications.open', $n->id) }}">
        <span class="bell-tag {{ $p['kind'] }}">{{ $p['tag'] }}</span>
        <span class="bell-text">{{ $p['text'] }}</span>
        @if($p['detail'])<span class="bell-detail">{{ $p['detail'] }}</span>@endif
        <span class="bell-time" title="{{ $n->created_at->format('d M Y H:i') }}">{{ $n->created_at->diffForHumans() }}</span>
    </a>
@empty
    <div class="bell-empty">No notifications yet.</div>
@endforelse
