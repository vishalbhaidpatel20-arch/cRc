<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
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
        return User::orderBy('role')->orderBy('full_name')->orderBy('user_id');
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
            'companies' => Company::where('is_active', 1)->orderBy('name')->get(),   // for the pop-up
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'companies' => Company::where('is_active', 1)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'all_companies' => ['required', 'boolean'],
            'companies' => ['exclude_if:all_companies,1', 'required', 'array', 'min:1'],
            'companies.*' => ['integer', 'exists:companies,company_id'],
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
