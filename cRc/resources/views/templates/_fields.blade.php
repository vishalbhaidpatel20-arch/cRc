{{--
  Template fields, shared by the pop-ups (create + one edit pop-up per row) and the stand-alone page.
  Expects $template. Pass $scope (the pop-up's marker) when several pop-ups share a page, so typed values
  and errors from a failed submit only show in the pop-up that was submitted.
--}}
@php($active = ! isset($scope) || old('_modal') === $scope)
@php($val = fn ($key) => $active ? old($key, $template->{$key}) : $template->{$key})
@php($err = fn ($key) => $active ? $errors->first($key) : '')

<label>Name *</label>
<input name="name" value="{{ $val('name') }}" required maxlength="120">
@if($err('name'))<div class="error">{{ $err('name') }}</div>@endif

<label>Channel *</label>
<select name="channel">
    @foreach(\App\Models\MessageTemplate::CHANNELS as $ch)<option value="{{ $ch }}" @selected($val('channel') === $ch)>{{ $ch }}</option>@endforeach
</select>
@if($err('channel'))<div class="error">{{ $err('channel') }}</div>@endif

<label>Category</label>
<select name="category">
    @foreach(\App\Models\MessageTemplate::CATEGORIES as $c)<option value="{{ $c }}" @selected($val('category') === $c)>{{ str_replace('_', ' ', $c) }}</option>@endforeach
</select>

<label>Subject (email only)</label>
<input name="subject" value="{{ $val('subject') }}" maxlength="200">
@if($err('subject'))<div class="error">{{ $err('subject') }}</div>@endif

<label>Message *</label>
<textarea name="body" rows="7" required maxlength="5000" style="width:100%;padding:.5rem;border:1px solid #cbd2d9;border-radius:6px;margin:.3rem 0 .6rem">{{ $val('body') }}</textarea>
@if($err('body'))<div class="error">{{ $err('body') }}</div>@endif
