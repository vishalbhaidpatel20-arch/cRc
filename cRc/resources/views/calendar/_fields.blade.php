{{--
  Fields of a calendar plan, shared by the "Plan something" pop-up and one edit pop-up per plan.
  Expects $event (new or existing), $tz (the viewer's timezone), $day (default date for a new plan, Y-m-d), $reminders.
  Pass $scope (the pop-up's marker) so typed values and errors from a failed submit only show in the pop-up that was submitted.
--}}
@php($active = ! isset($scope) || old('_modal') === $scope)
@php($failed = $active && old('_modal') !== null)
@php($local = $event->exists ? \App\Support\UserTime::local($event->start_at, $tz) : null)
@php($val = fn ($key, $default) => $failed ? old($key, $default) : $default)
@php($err = fn ($key) => $active ? $errors->first($key) : '')
@php($allDay = $failed ? (bool) old('all_day') : (bool) $event->all_day)
@php($remind = $failed ? old('remind_minutes') : ($event->exists ? $event->remind_minutes : 15))

<input type="hidden" name="tz" value="{{ $tz }}" data-tz>

<label>What are you planning? *</label>
<input name="title" value="{{ $val('title', $event->title) }}" required maxlength="150" placeholder="For example: call the Bluewave team about the invoice fix">
@if($err('title'))<div class="error">{{ $err('title') }}</div>@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:.7rem">
    <div>
        <label>Date *</label>
        <input type="date" name="date" value="{{ $val('date', $local?->format('Y-m-d') ?? $day) }}" required>
        @if($err('date'))<div class="error">{{ $err('date') }}</div>@endif
    </div>
    <div>
        <label>Time</label>
        <input type="time" name="time" value="{{ $val('time', ($event->exists && ! $event->all_day) ? $local->format('H:i') : '09:00') }}">
        @if($err('time'))<div class="error">{{ $err('time') }}</div>@endif
    </div>
</div>
<label style="display:flex;align-items:center;gap:.5rem;margin:.1rem 0 .7rem;font-weight:400">
    <input type="checkbox" name="all_day" value="1" @checked($allDay) style="width:auto;margin:0"> All day (the reminder comes at 9:00)
</label>

<label>Remind me</label>
<select name="remind_minutes">
    <option value="" @selected($remind === null || $remind === '')>No reminder</option>
    @foreach($reminders as $m)
        <option value="{{ $m }}" @selected($remind !== null && $remind !== '' && (int) $remind === $m)>{{ \App\Models\CalendarEvent::reminderLabel($m) }}</option>
    @endforeach
</select>
@if($err('remind_minutes'))<div class="error">{{ $err('remind_minutes') }}</div>@endif

<label>Notes</label>
<textarea name="notes" rows="3" maxlength="2000" placeholder="Anything you want to remember (optional)" style="width:100%;padding:.5rem;border:1px solid #cbd2d9;border-radius:6px;margin:.3rem 0 .6rem">{{ $val('notes', $event->notes) }}</textarea>
@if($err('notes'))<div class="error">{{ $err('notes') }}</div>@endif
