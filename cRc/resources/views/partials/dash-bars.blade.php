{{--
  A small horizontal bar chart: one row per item, the bar scaled to the biggest value.
  Expects $rows = [['label' => 'Critical', 'value' => 3, 'color' => '#d94b4b', 'href' => null], ...]
  Shows a friendly line when everything is zero.
--}}
@php($max = max(1, collect($rows)->max('value')))
@php($sum = collect($rows)->sum('value'))
@if($sum === 0)
    <p class="muted" style="margin:.8rem 0 0">{{ $empty ?? 'Nothing here yet.' }}</p>
@else
    <div class="dbars">
        @foreach($rows as $r)
            @php($tag = ! empty($r['href']) ? 'a' : 'div')
            <{{ $tag }} class="dbar" @if(! empty($r['href'])) href="{{ $r['href'] }}" @endif>
                <span class="dbar-label">{{ $r['label'] }}</span>
                <span class="dbar-track"><span class="dbar-fill" style="width:{{ round($r['value'] / $max * 100) }}%;background:{{ $r['color'] }}"></span></span>
                <span class="dbar-n">{{ $r['value'] }}</span>
            </{{ $tag }}>
        @endforeach
    </div>
@endif
