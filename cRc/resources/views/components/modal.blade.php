{{--
  Reusable pop-up form (native <dialog>). Usage:
    <a id="new-x-link" data-modal="x-modal" href="{{ route('x.create') }}">+ New x</a>   (works without JavaScript)
    <x-modal id="x-modal" marker="x" title="New x" :action="route('x.store')" submit="Create x" :open="$openModal">
        ...fields...
    </x-modal>
  The pop-up re-opens by itself when a submit from it failed validation (old('_modal') === marker),
  or when $open is true (for example ?new=1 on the list page).
--}}
@props(['id', 'marker', 'title', 'action', 'submit' => 'Create', 'note' => null, 'open' => false, 'wide' => false, 'method' => 'POST'])

@php($marker = $marker ?? $id)
<dialog id="{{ $id }}" class="modal {{ $wide ? 'wide' : '' }}" aria-labelledby="{{ $id }}-title">
    <form method="POST" action="{{ $action }}" class="f" data-modal-form>
        @csrf
        @if(strtoupper($method) !== 'POST') @method($method) @endif
        <input type="hidden" name="_modal" value="{{ $marker }}">
        <div class="modal-head">
            <h3 id="{{ $id }}-title" style="margin:0">{{ $title }}</h3>
            <button type="button" class="modal-x" data-close aria-label="Close">&times;</button>
        </div>
        @if($note)<p class="muted" style="margin:.2rem 0 .8rem">{{ $note }}</p>@endif

        {{ $slot }}

        <div class="modal-foot">
            <button type="button" class="btn outline" data-close>Cancel</button>
            <button type="submit" class="btn" data-submit>{{ $submit }}</button>
        </div>
    </form>
</dialog>

<script>
(function () {
    var modal = document.getElementById(@json($id));
    var link = document.querySelector('[data-modal="' + @json($id) + '"]');
    if (!modal || typeof modal.showModal !== 'function') { return; }   // old browser: the link still opens the page

    function open() { if (!modal.open) { modal.showModal(); } }
    function close() { modal.close(); }

    if (link) { link.addEventListener('click', function (e) { e.preventDefault(); open(); }); }
    modal.querySelectorAll('[data-close]').forEach(function (b) { b.addEventListener('click', close); });
    // A click on the dimmed backdrop (the dialog element itself) closes it.
    modal.addEventListener('click', function (e) { if (e.target === modal) { close(); } });
    // One click, one record: stop a double submit.
    modal.querySelector('[data-modal-form]').addEventListener('submit', function () {
        var b = modal.querySelector('[data-submit]');
        b.disabled = true; b.textContent = 'Saving...';
    });

    @if($open || ($errors->any() && old('_modal') === $marker))
    open();   // the pop-up was submitted with mistakes (or asked for): show it with the errors and what was typed
    @endif
})();
</script>
