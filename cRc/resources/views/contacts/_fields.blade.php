{{-- Contact fields, shared by the pop-up, the stand-alone page and the edit page. Expects $contact, $companies, $allModules. --}}
<label>Company *</label>
<select name="company_id" required>
    <option value="">Select a company</option>
    @foreach($companies as $c)
        <option value="{{ $c->company_id }}" @selected(old('company_id', $contact->company_id) == $c->company_id)>{{ $c->name }}</option>
    @endforeach
</select>
@error('company_id')<div class="error">{{ $message }}</div>@enderror

<label>Full name *</label>
<input name="full_name" value="{{ old('full_name', $contact->full_name) }}" required>
@error('full_name')<div class="error">{{ $message }}</div>@enderror

<label>Email *</label>
<input name="email" type="email" value="{{ old('email', $contact->email) }}" required>
@error('email')<div class="error">{{ $message }}</div>@enderror

<label>Phone</label>
<input name="phone" value="{{ old('phone', $contact->phone) }}">

<label>WhatsApp number</label>
<input name="whatsapp_no" value="{{ old('whatsapp_no', $contact->whatsapp_no) }}">

<label>Position</label>
<input name="position" value="{{ old('position', $contact->position) }}">

<label>Department</label>
<input name="department" value="{{ old('department', $contact->department) }}">

<label>Business name</label>
<input name="business_name" value="{{ old('business_name', $contact->business_name) }}">

<label>Role in the product</label>
<select name="product_role">
    @foreach(\App\Models\Contact::PRODUCT_ROLES as $r)
        <option value="{{ $r }}" @selected(old('product_role', $contact->product_role ?? 'end_user') === $r)>{{ str_replace('_', ' ', $r) }}</option>
    @endforeach
</select>
@error('product_role')<div class="error">{{ $message }}</div>@enderror

<label>Preferred channel</label>
<select name="preferred_channel">
    @foreach(\App\Models\Contact::CHANNELS as $ch)
        <option value="{{ $ch }}" @selected(old('preferred_channel', $contact->preferred_channel ?? 'email') === $ch)>{{ $ch }}</option>
    @endforeach
</select>

<label>Best time to reach</label>
<input name="preferred_time" value="{{ old('preferred_time', $contact->profile?->preferred_time) }}" placeholder="e.g. 10am to 1pm">

<label>Uses these modules</label>
@php($chosenModules = old('modules', $contact->exists ? $contact->modules->pluck('module_id')->all() : []))
<div style="margin:.3rem 0 .8rem">
    @forelse($allModules as $m)
        <label style="display:inline-flex;align-items:center;gap:.3rem;margin-right:1rem;font-weight:400">
            <input type="checkbox" name="modules[]" value="{{ $m->module_id }}" style="width:auto;margin:0" @checked(in_array($m->module_id, $chosenModules))> {{ $m->name }}
        </label>
    @empty
        <span class="muted">No modules yet. An admin can add them under Modules.</span>
    @endforelse
</div>
@error('modules.*')<div class="error">{{ $message }}</div>@enderror

<label style="display:block;margin-bottom:1rem">
    <input type="checkbox" name="is_primary" value="1" style="width:auto;margin:0 .4rem 0 0"
        @checked(old('is_primary', $contact->is_primary))> Primary contact for this company
</label>
