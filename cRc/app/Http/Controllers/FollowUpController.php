<?php

namespace App\Http\Controllers;

use App\Models\FollowUp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FollowUpController extends Controller
{
    /** Only the agent a follow-up belongs to, or an admin, can mark it done. */
    public function done(Request $request, FollowUp $followUp): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $followUp->assigned_to === $request->user()->user_id, 403);

        if ($followUp->status === 'pending') {
            $followUp->update(['status' => 'done', 'completed_at' => now()]);
        }

        return back()->with('status', 'Follow-up marked done.');
    }
}
