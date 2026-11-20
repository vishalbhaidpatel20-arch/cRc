<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Services\AccountRemoval;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Only admins reach this controller (routes are behind role:admin).
 * There is no public registration: every agent account is created here.
 */
class UserController extends Controller
{
    /** The list order, with the id as a tie-break so pages never overlap. */
    private function ordered()
    {
        // Soft-deleted agents are left out automatically (SoftDeletes).
        return User::query()->orderBy('role')->orderBy('full_name')->orderBy('user_id');
    }

    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q'));

        $users = $this->ordered()
            ->when($term !== '', function ($query) use ($term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn ($q) => $q->where('full_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('role', 'like', $like));
            })
            ->paginate(config('crc.per_page'))
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            // Soft-deleted agents, newest first, for the "Deleted agents" list with its Restore buttons.
            'deleted' => User::onlyTrashed()->where('role', User::ROLE_AGENT)->orderByDesc('deleted_at')->orderBy('user_id')->get(),
            'companies' => Company::active()->orderBy('name')->get(),   // for the pop-up
        ]);
    }

    /**
     * Admin restores a deleted agent: deleted_at is cleared and the account is active again, so they can sign in and appear in
     * lists and pickers. Tickets that went back to the queue and follow-ups that were cancelled are NOT handed back
     * automatically: the admin reassigns what is still needed.
     */
    public function restore(User $user): RedirectResponse
    {
        abort_unless($user->isAgent() && $user->trashed(), 404);

        DB::transaction(function () use ($user) {
            $user->restore();
            $user->forceFill(['is_active' => true])->save();
        });

        $position = $this->ordered()->pluck('user_id')->search($user->user_id);
        $page = intdiv((int) $position, config('crc.per_page')) + 1;

        return redirect()->route('admin.users.index', $page > 1 ? ['page' => $page] : [])
            ->with('status', "Agent {$user->full_name} restored and can sign in again. Their old tickets stay in the unassigned queue until you reassign them.")
            ->with('highlight', $user->user_id);
    }

    /** Admin deletes an agent (never an admin): see AccountRemoval for what that means. */
    public function destroy(Request $request, User $user, AccountRemoval $removal): RedirectResponse
    {
        abort_unless($user->isAgent() && ! $user->isRemoved(), 404);

        $name = $user->full_name;
        $removal->remove($user, $request->user());

        return redirect()->route('admin.users.index')->with('status', "Agent {$name} deleted. Their open tickets are back in the unassigned queue.");
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'companies' => Company::active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            // unique:users also counts soft-deleted agents (their row keeps the address), so the message says so.
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'all_companies' => ['required', 'boolean'],
            'companies' => ['exclude_if:all_companies,1', 'required', 'array', 'min:1'],
            'companies.*' => ['integer', 'exists:companies,company_id'],
        ], [
            'email.unique' => 'This email is already used, possibly by a deleted agent (deleted accounts keep their address).',
        ]);

        $agent = DB::transaction(function () use ($data, $request) {
            // role is forced to "agent" here: it is never read from the request.
            $agent = User::create([
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password_hash' => $data['password'],
                'role' => User::ROLE_AGENT,
                'all_companies' => (bool) $data['all_companies'],
                'is_active' => true,
                'created_by' => $request->user()->user_id,
            ]);

            if (! $agent->all_companies) {
                $agent->companies()->sync($data['companies']);
            }

            return $agent;
        });

        // Back to the list, on the page where the new row sits, with it highlighted.
        $position = $this->ordered()->pluck('user_id')->search($agent->user_id);
        $page = intdiv((int) $position, config('crc.per_page')) + 1;

        return redirect()->route('admin.users.index', $page > 1 ? ['page' => $page] : [])
            ->with('status', "Agent {$data['full_name']} created.")
            ->with('highlight', $agent->user_id);
    }
}
