{{--
  Dashboard stat card: an icon tile, the number and a label. Usage:
    <x-stat tone="warn" icon="inbox" :value="$n" label="Unassigned" hint="need an owner" :href="route('tickets.index')" />
  tone: brand | warn | bad | good | info   (the tile and the number take the tone colour)
  icon: ticket | inbox | alert | clock | chat | bell | check
  Without href it is a plain block instead of a link.
--}}
@props(['tone' => 'brand', 'icon' => 'ticket', 'label', 'value', 'hint' => null, 'href' => null])
@php($tag = $href ? 'a' : 'div')
<{{ $tag }} class="dstat tone-{{ $tone }}" @if($href) href="{{ $href }}" @endif>
    <span class="dstat-ico" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            @switch($icon)
                @case('inbox') <path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/> @break
                @case('alert') <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/> @break
                @case('clock') <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/> @break
                @case('chat') <path d="M21 11.5a8.4 8.4 0 0 1-12.4 7.4L3 21l1.9-5.4A8.5 8.5 0 1 1 21 11.5z"/> @break
                @case('bell') <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/> @break
                @case('check') <path d="M20 6 9 17l-5-5"/> @break
                @default <path d="M15 5v2"/><path d="M15 11v2"/><path d="M15 17v2"/><path d="M5 5h14a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4V7a2 2 0 0 1 2-2z"/>
            @endswitch
        </svg>
    </span>
    <span class="dstat-body">
        <span class="dstat-n">{{ $value }}</span>
        <span class="dstat-l">{{ $label }}</span>
        @if($hint)<span class="dstat-h">{{ $hint }}</span>@endif
    </span>
</{{ $tag }}>
