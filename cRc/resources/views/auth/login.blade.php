@extends('layouts.app')
@section('title', 'Log in')

@section('content')
<div class="card" style="max-width:380px;margin:4rem auto">
    <img src="{{ asset('images/crc-logo.svg') }}" alt="cRc, Customer Relationship Console"
         style="display:block;width:250px;max-width:100%;margin:0 auto 1rem">
    <h2 style="margin-top:0">Log in</h2>
    <form method="POST" action="{{ url('/login') }}">
        @csrf
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
               style="width:100%;padding:.55rem;margin:.3rem 0 .2rem;border:1px solid #cbd2d9;border-radius:6px">
        @error('email')<div class="error">{{ $message }}</div>@enderror

        <label for="password" style="display:block;margin-top:.8rem">Password</label>
        <input id="password" name="password" type="password" required
               style="width:100%;padding:.55rem;margin:.3rem 0 .2rem;border:1px solid #cbd2d9;border-radius:6px">
        @error('password')<div class="error">{{ $message }}</div>@enderror

        <button type="submit"
                style="width:100%;margin-top:1.2rem;padding:.65rem;background:#1F1A5C;color:#fff;border:0;border-radius:6px;cursor:pointer">
            Log in
        </button>
    </form>
</div>
@endsection
