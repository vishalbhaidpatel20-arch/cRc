{{-- New-agent fields, shared by the pop-up and the stand-alone page. Expects $companies. --}}
<label>Full name *</label>
<input name="full_name" value="{{ old('full_name') }}" required maxlength="120">
@error('full_name')<div class="error">{{ $message }}</div>@enderror

<label>Email *</label>
<input name="email" type="email" value="{{ old('email') }}" required maxlength="190">
@error('email')<div class="error">{{ $message }}</div>@enderror

<label>Phone</label>
<input name="phone" value="{{ old('phone') }}" maxlength="30">
@error('phone')<div class="error">{{ $message }}</div>@enderror

<label>Temporary password * (min 8 characters)</label>
<input name="password" type="password" required autocomplete="new-password">
@error('password')<div class="error">{{ $message }}</div>@enderror

<label>Confirm password *</label>
<input name="password_confirmation" type="password" required autocomplete="new-password">

<label>Company access</label>
<select name="all_companies">
    <option value="1" @selected(old('all_companies', '1') == '1')>All companies</option>
    <option value="0" @selected(old('all_companies') === '0')>Only selected companies</option>
</select>
<div style="margin:.2rem 0 .6rem">
    @foreach($companies as $c)
        <label style="display:block;font-weight:400"><input type="checkbox" name="companies[]" value="{{ $c->company_id }}" style="width:auto;margin:0 .4rem 0 0"
            @checked(in_array($c->company_id, old('companies', [])))> {{ $c->name }}</label>
    @endforeach
</div>
@error('companies')<div class="error">{{ $message }}</div>@enderror
