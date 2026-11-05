{{-- Renders a module's form from App\Support\ModuleForm. Expects $module ('company' or 'contact'), $model and optional $data. --}}
@foreach(\App\Support\ModuleForm::fields($module, $model, $data ?? []) as $f)
    @php($errorKey = $f['error'] ?? $f['name'])
    @switch($f['type'])
        @case('select')
            <label>{{ $f['label'] }}{{ ($f['required'] ?? false) ? ' *' : '' }}</label>
            <select name="{{ $f['name'] }}" {!! ($f['required'] ?? false) ? 'required' : '' !!}>
                @isset($f['empty'])<option value="">{{ $f['empty'] }}</option>@endisset
                @foreach($f['options'] as $optValue => $optLabel)
                    <option value="{{ $optValue }}" @selected((string) old($f['name'], $f['value']) === (string) $optValue)>{{ $optLabel }}</option>
                @endforeach
            </select>
            @error($errorKey)<div class="error">{{ $message }}</div>@enderror
            @break
        @case('checkboxes')
            @php($chosen = old($f['name'], $f['value']))
            <label>{{ $f['label'] }}</label>
            <div style="margin:.3rem 0 .8rem">
                @forelse($f['options'] as $optValue => $optLabel)
                    <label style="display:inline-flex;align-items:center;gap:.3rem;margin-right:1rem;font-weight:400">
                        <input type="checkbox" name="{{ $f['name'] }}[]" value="{{ $optValue }}" style="width:auto;margin:0" @checked(in_array($optValue, $chosen))> {{ $optLabel }}
                    </label>
                @empty
                    <span class="muted">{{ $f['none'] ?? '' }}</span>
                @endforelse
            </div>
            @error($errorKey)<div class="error">{{ $message }}</div>@enderror
            @break
        @case('checkbox')
            <label style="display:block;margin-bottom:1rem">
                <input type="checkbox" name="{{ $f['name'] }}" value="1" style="width:auto;margin:0 .4rem 0 0" @checked(old($f['name'], $f['value']))> {{ $f['label'] }}
            </label>
            @break
        @default
            @include('partials._text-field', ['name' => $f['name'], 'label' => $f['label'], 'value' => $f['value'], 'type' => $f['type'], 'required' => $f['required'] ?? false, 'maxlength' => $f['maxlength'] ?? null, 'placeholder' => $f['placeholder'] ?? null])
    @endswitch

@endforeach
