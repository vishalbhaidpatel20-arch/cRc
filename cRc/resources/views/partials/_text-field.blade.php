{{-- One labelled text input with its validation error. Required: $name, $label, $value. Optional: $type (default text), $maxlength, $required, $placeholder. --}}
<label>{{ $label }}{{ ($required ?? false) ? ' *' : '' }}</label>
<input name="{{ $name }}" {!! ($type ?? 'text') !== 'text' ? 'type="'.e($type).'"' : '' !!} value="{{ old($name, $value) }}" {!! ($required ?? false) ? 'required' : '' !!} {!! ! empty($maxlength) ? 'maxlength="'.e($maxlength).'"' : '' !!} {!! ! empty($placeholder) ? 'placeholder="'.e($placeholder).'"' : '' !!}>
@error($name)<div class="error">{{ $message }}</div>@enderror
