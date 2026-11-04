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
@php($shouldOpen = $open || ($errors->any() && old('_modal') === $marker))
<dialog id="{{ $id }}" class="modal {{ $wide ? 'wide' : '' }}" aria-labelledby="{{ $id }}-title" @if($shouldOpen) data-open @endif>
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
    if (!modal || typeof modal.showModal !== 'function') { return; }   // old browser: the link still opens the page
    // Opening from the "+ New ..." / "Edit" link is handled once for the whole page by js/partial.js.

    function open() { if (!modal.open) { modal.showModal(); } }
    // Delegated, so it keeps working when partial.js refreshes the fields inside the pop-up.
    modal.addEventListener('click', function (e) {
        if (e.target === modal || (e.target.closest && e.target.closest('[data-close]'))) { modal.close(); }
    });
    // One click, one record: stop a double submit (partial.js puts the button back after the reply).
    modal.querySelector('[data-modal-form]').addEventListener('submit', function () {
        var b = modal.querySelector('[data-submit]');
        b.dataset.label = b.textContent;
        b.disabled = true; b.textContent = 'Saving...';
    });

    if (modal.hasAttribute('data-open')) { open(); }   // submitted with mistakes (or asked for): show it with the errors
})();
</script>
