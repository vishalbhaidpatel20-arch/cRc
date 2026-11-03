{{--
  "Log a conversation" panel (contact page and ticket page).
  Expects: $action, $anchorUrl, $templates, $selected, $rendered, $renderedAll, and either
  $contactChoices (ticket page: who you spoke with) or $ticketChoices (contact page: optional ticket).
--}}
@php($pickedChannel = old('channel', $selected?->channel ?? 'call'))
<div id="log-conversation" class="log-panel">
    <h3>Log a conversation</h3>

    <form method="POST" action="{{ $action }}">
        @csrf

        <div class="row3">
            <select name="channel" aria-label="Channel">
                @foreach(\App\Models\Interaction::CHANNELS as $ch)
                    <option value="{{ $ch }}" @selected($pickedChannel === $ch)>{{ \App\Models\Interaction::channelLabel($ch) }}</option>
                @endforeach
            </select>
            <select name="direction" aria-label="Direction">
                @foreach(\App\Models\Interaction::DIRECTIONS as $d)
                    <option value="{{ $d }}" @selected(old('direction', $selected ? 'outbound' : 'inbound') === $d)>{{ ucfirst($d) }}</option>
                @endforeach
            </select>
            <select name="topic" aria-label="Topic">
                @foreach(\App\Models\Interaction::TOPICS as $tp)
                    <option value="{{ $tp }}" @selected(old('topic', $selected?->category ?? $defaultTopic ?? 'query') === $tp)>Topic: {{ ucfirst(str_replace('_', ' ', $tp)) }}</option>
                @endforeach
            </select>
        </div>
        @foreach(['channel', 'direction', 'topic'] as $f)@error($f)<div class="error">{{ $message }}</div>@enderror @endforeach

        @isset($contactChoices)
            <label for="contact_id">Spoke with</label>
            <select id="contact_id" name="contact_id" required>
                @foreach($contactChoices as $c)
                    <option value="{{ $c->contact_id }}" @selected(old('contact_id', $defaultContactId ?? null) == $c->contact_id)>{{ $c->full_name }}</option>
                @endforeach
            </select>
            @error('contact_id')<div class="error">{{ $message }}</div>@enderror
        @endisset

        @isset($ticketChoices)
            <label for="ticket_id">About ticket (optional)</label>
            <select id="ticket_id" name="ticket_id">
                <option value="">No ticket</option>
                @foreach($ticketChoices as $t)
                    <option value="{{ $t->ticket_id }}" @selected(old('ticket_id') == $t->ticket_id)>{{ $t->ticket_no }}: {{ $t->title }}</option>
                @endforeach
            </select>
            @error('ticket_id')<div class="error">{{ $message }}</div>@enderror
        @endisset

        {{-- No name: this select is only a helper, it is not submitted. --}}
        <select id="tpl-picker" aria-label="Insert message template" style="margin-top:.6rem">
            <option value="">Insert message template...</option>
            @foreach($templates as $tpl)
                <option value="{{ $tpl->template_id }}"
                        data-body="{{ $renderedAll[$tpl->template_id]['body'] ?? '' }}"
                        data-subject="{{ $renderedAll[$tpl->template_id]['subject'] ?? '' }}"
                        data-channel="{{ $tpl->channel }}"
                        data-topic="{{ $tpl->category }}"
                        @selected($selected?->template_id === $tpl->template_id)>{{ $tpl->name }} ({{ $tpl->channel }})</option>
            @endforeach
        </select>
        <div id="tpl-subject" class="muted" style="font-size:.85rem;margin-top:.3rem">@if($selected && $rendered['subject'])Email subject: {{ $rendered['subject'] }}@endif</div>

        <textarea name="summary" rows="4" required placeholder="What did you discuss?" style="margin-top:.6rem">{{ old('summary', $rendered['body'] ?? '') }}</textarea>
        @error('summary')<div class="error">{{ $message }}</div>@enderror

        <details style="margin-top:.5rem">
            <summary class="muted" style="cursor:pointer;font-size:.85rem">Set a different time (default: now)</summary>
            <input name="interaction_at" type="datetime-local" value="{{ old('interaction_at') }}" style="margin-top:.4rem;width:auto">
        </details>
        @error('interaction_at')<div class="error">{{ $message }}</div>@enderror

        <div class="log-foot">
            <label class="fu">Follow up on
                <input type="date" name="follow_up_on" value="{{ old('follow_up_on') }}" min="{{ now()->toDateString() }}">
            </label>
            <button class="btn outline" type="submit">Save log</button>
        </div>
        @error('follow_up_on')<div class="error">{{ $message }}</div>@enderror
    </form>

    {{-- Without JavaScript the template picker above cannot fill the box; this reloads the page instead. --}}
    <noscript>
        <form method="GET" action="{{ $anchorUrl }}" class="filters">
            <select name="template" style="width:auto">
                <option value="">Use a template</option>
                @foreach($templates as $tpl)<option value="{{ $tpl->template_id }}">{{ $tpl->name }} ({{ $tpl->channel }})</option>@endforeach
            </select>
            <button class="btn outline" type="submit">Fill from template</button>
        </form>
    </noscript>
</div>

<script>
(function () {
    var picker = document.getElementById('tpl-picker');
    if (!picker) { return; }
    picker.addEventListener('change', function () {
        var opt = picker.options[picker.selectedIndex];
        var form = picker.form;
        var note = document.getElementById('tpl-subject');
        if (!opt.value) { if (note) { note.textContent = ''; } return; }
        form.elements['summary'].value = opt.dataset.body || '';
        if (opt.dataset.channel) { form.elements['channel'].value = opt.dataset.channel; }
        if (opt.dataset.topic) { form.elements['topic'].value = opt.dataset.topic; }
        form.elements['direction'].value = 'outbound';
        if (note) { note.textContent = opt.dataset.subject ? 'Email subject: ' + opt.dataset.subject : ''; }
    });
})();
</script>
