{{--
  Left icon rail, always visible on signed-in pages, beside the content (not in the header), centred under the header logo.
  A "Tickets" heading, then one tile per ticket status: its own colour (the same as the status pills in the tables), an icon,
  and a short label (no counts). The full name is the tooltip and the link's accessible name.
  Links go to the ticket list filtered by status. The active tile is rendered here; data-sync: partial.js refreshes it
  after every in-place navigation, and public/js/sidebar.js also re-marks it from the URL.
--}}
@php
    $sideActive = request()->routeIs('tickets.index') ? request()->query('status', 'open') : null;
    $sideLinks = [
        // list
        ['status' => 'all', 'short' => 'All', 'label' => 'All tickets',
         'icon' => '<path d="M9 6h11"/><path d="M9 12h11"/><path d="M9 18h11"/><circle cx="4.5" cy="6" r="1"/><circle cx="4.5" cy="12" r="1"/><circle cx="4.5" cy="18" r="1"/>'],
        // ticket stub
        ['status' => 'open', 'short' => 'Open', 'label' => 'Open tickets',
         'icon' => '<path d="M3 8a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4z"/><path d="M14 6v12" stroke-dasharray="2 2"/>'],
        // turning arrows
        ['status' => 'in_progress', 'short' => 'In progress', 'label' => 'In progress tickets',
         'icon' => '<path d="M20 12a8 8 0 0 1-14 5.3"/><path d="M4 12a8 8 0 0 1 14-5.3"/><path d="M18 3v4h-4"/><path d="M6 21v-4h4"/>'],
        // clock
        ['status' => 'waiting', 'short' => 'Waiting', 'label' => 'Waiting tickets',
         'icon' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>'],
        // check in a circle
        ['status' => 'resolved', 'short' => 'Resolved', 'label' => 'Resolved tickets',
         'icon' => '<circle cx="12" cy="12" r="8.5"/><path d="m8.3 12.3 2.6 2.6 4.8-5"/>'],
    ];
@endphp
<aside id="side-menu" class="side-menu" aria-label="Tickets by status" data-sync>
    <div class="side-head" aria-hidden="true">Tickets</div>
    @foreach($sideLinks as $link)
        <a @class(['side-link', 'st-'.$link['status'], 'active' => $sideActive === $link['status']])
           href="{{ route('tickets.index', ['status' => $link['status']]) }}"
           data-side-status="{{ $link['status'] }}" data-swap-main
           aria-label="{{ $link['label'] }}" title="{{ $link['label'] }}"
           @if($sideActive === $link['status']) aria-current="page" @endif>
            <span class="side-ico">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $link['icon'] !!}</svg>
            </span>
            <span class="side-label" aria-hidden="true">{{ $link['short'] }}</span>
        </a>
    @endforeach
</aside>
