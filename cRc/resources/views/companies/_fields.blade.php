{{-- Company form fields, shared by the pop-up on the list page and the stand-alone form page. Expects $company. --}}
<label>Name *</label>
<input name="name" value="{{ old('name', $company->name) }}" required maxlength="150">
@error('name')<div class="error">{{ $message }}</div>@enderror

<label>Industry</label>
<input name="industry" value="{{ old('industry', $company->industry) }}" maxlength="100">
@error('industry')<div class="error">{{ $message }}</div>@enderror

<label>Email</label>
<input name="email" type="email" value="{{ old('email', $company->email) }}" maxlength="190">
@error('email')<div class="error">{{ $message }}</div>@enderror

<label>Phone</label>
<input name="phone" value="{{ old('phone', $company->phone) }}" maxlength="30">
@error('phone')<div class="error">{{ $message }}</div>@enderror

<label>City</label>
<input name="city" value="{{ old('city', $company->city) }}" maxlength="80">
@error('city')<div class="error">{{ $message }}</div>@enderror
