@extends('layouts.app')
@section('title', 'New agent')

@section('content')
@php($input = 'width:100%;padding:.55rem;margin:.3rem 0 .6rem;border:1px solid #cbd2d9;border-radius:6px')
<div class="card" style="max-width:520px">
    <h2 style="margin-top:0">New agent</h2>
    <form method="POST" action="{{ route('admin.users.store') }}">
        @csrf
        <label>Full name</label>
        <input name="full_name" value="{{ old('full_name') }}" required style="{{ $input }}">
        @error('full_name')<div class="error">{{ $message }}</div>@enderror

        <label>Email</label>
        <input name="email" type="email" value="{{ old('email') }}" required style="{{ $input }}">
        @error('email')<div class="error">{{ $message }}</div>@enderror

        <label>Phone</label>
        <input name="phone" value="{{ old('phone') }}" style="{{ $input }}">

        <label>Temporary password (min 8 characters)</label>
        <input name="password" type="password" required style="{{ $input }}">
        @error('password')<div class="error">{{ $message }}</div>@enderror

        <label>Confirm password</label>
        <input name="password_confirmation" type="password" required style="{{ $input }}">

        <label>Company access</label>
        <select name="all_companies" style="{{ $input }}">
            <option value="1" @selected(old('all_companies', '1') == '1')>All companies</option>
            <option value="0" @selected(old('all_companies') === '0')>Only selected companies</option>
        </select>
        @foreach($companies as $c)
            <label style="display:block"><input type="checkbox" name="companies[]" value="{{ $c->company_id }}"
                @checked(in_array($c->company_id, old('companies', [])))> {{ $c->name }}</label>
        @endforeach
        @error('companies')<div class="error">{{ $message }}</div>@enderror

        <button type="submit" style="margin-top:1rem;padding:.6rem 1.2rem;background:#1f3a5f;color:#fff;border:0;border-radius:6px;cursor:pointer">Create agent</button>
        <a href="{{ route('admin.users.index') }}" style="margin-left:1rem">Cancel</a>
    </form>
</div>
@endsection
