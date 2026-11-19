@extends('layouts.app')
@section('title', 'My account')

@section('content')
<div class="card" style="max-width:34rem">
    <h2 style="margin:0">My account</h2>
    <p class="muted" style="margin:.3rem 0 1rem">{{ auth()->user()->full_name }} &middot; {{ auth()->user()->email }}</p>

    <h3 style="margin:0 0 .4rem;color:#b42318">Delete my account</h3>
    <p style="margin:0 0 .6rem">
        This signs you out and you can no longer sign in; only an admin can restore your account.
        Your past conversations and ticket history stay in the log under your name, your pending follow-ups are cancelled,
        and the tickets you still hold go back to the unassigned queue for an admin to hand out.
    </p>

    {{-- A normal form post (data-no-ajax): the session ends here, so the page must really reload. --}}
    <form method="POST" action="{{ route('account.destroy') }}" data-no-ajax
          onsubmit="return confirm('Delete your account? You will be signed out and only an admin can restore it.')">
        @csrf
        @method('DELETE')
        <label for="account-password">Type your password to confirm</label>
        <input id="account-password" type="password" name="password" required autocomplete="current-password" style="display:block;margin:.3rem 0 .4rem;max-width:18rem">
        @error('password')<div class="error" style="color:#b42318;margin-bottom:.4rem">{{ $message }}</div>@enderror
        <button class="btn" type="submit" style="background:#b42318">Delete my account</button>
    </form>
</div>
@endsection
