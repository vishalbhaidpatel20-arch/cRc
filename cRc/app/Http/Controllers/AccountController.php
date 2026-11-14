<?php

namespace App\Http\Controllers;

use App\Services\AccountRemoval;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/** An agent's own account page; the only thing on it is deleting the account (agent routes only). */
class AccountController extends Controller
{
    public function show(): View
    {
        return view('account.show');
    }

    public function destroy(Request $request, AccountRemoval $removal): RedirectResponse
    {
        $user = $request->user();

        $request->validate(['password' => ['required', 'string']]);

        if (! $user->password_hash || ! Hash::check($request->input('password'), $user->password_hash)) {
            return back()->withErrors(['password' => 'That password is not correct.']);
        }

        $removal->remove($user, $user);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => 'Your account has been deleted.']);
    }
}
