{{--
  Placeholder shown in place of a data grid until resources/js/grid.jsx is ready. Put it directly before the
  <table class="list" data-grid>. With JavaScript the table is hidden (CSS) and the placeholder holds exactly the grid's
  height; the grid then mounts inside the placeholder. Without JavaScript the placeholder is hidden and the table shows.
  Give :page-size (and the same data-page-size on the table) for a small grid embedded in a page.
  An empty list has no placeholder: its "No ... found" table stays visible.
--}}
@props(['total', 'pageSize' => null])
@if($total > 0)
<div class="grid-skeleton" data-total="{{ $total }}" @if($pageSize) data-page-size="{{ $pageSize }}" @endif aria-hidden="true"><div class="gs-head"></div>@for($i = 0; $i < min($total, $pageSize ?: 8); $i++)<div class="gs-row"><span></span></div>@endfor</div>
<script>window.crcFitSkeleton && crcFitSkeleton(document.currentScript.previousElementSibling);</script>
@endif
