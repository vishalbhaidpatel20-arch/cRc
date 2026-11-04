{{--
  Header bell: unread count, dropdown with the latest notifications, "Mark all as read" and a link to the full list.
  Server-rendered here; public/js/notifications.js keeps it current (polls /notifications/feed) and opens/closes the panel.
  Without JavaScript the bell is a plain link to /notifications.
--}}
@php($bellUser = auth()->user())
@php($bellUnread = $bellUser->unreadNotifications()->count())
<div class="bell" data-bell data-feed-url="{{ route('notifications.feed') }}" data-read-url="{{ route('notifications.read') }}">
    <a class="bell-btn" href="{{ route('notifications.index') }}" role="button" aria-label="Notifications" aria-haspopup="true" aria-expanded="false" data-bell-toggle>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
        <span class="bell-count" data-bell-count @if($bellUnread === 0) hidden @endif>{{ $bellUnread > 99 ? '99+' : $bellUnread }}</span>
    </a>
    <div class="bell-panel" data-bell-panel hidden>
        <div class="bell-head">
            <strong>Notifications</strong>
            <button type="button" class="bell-link" data-bell-mark-read>Mark all as read</button>
        </div>
        <div class="bell-list" data-bell-list>
            @include('partials.notification-items', ['items' => $bellUser->notifications()->latest()->latest('id')->limit(8)->get()])
        </div>
        <a class="bell-foot" href="{{ route('notifications.index') }}">See all notifications</a>
    </div>
</div>
<a class="bell-toast" data-bell-toast href="{{ route('notifications.index') }}" role="status" aria-live="polite" hidden></a>
