<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * index/show: any logged-in user. Everything else: admin only (see routes/web.php).
 * Companies are never deleted; they are deactivated (is_active).
 */
class CompanyController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): View
    {
        $status = $request->query('status', 'active');

        $companies = Company::query()
            ->withCount(['contacts' => fn ($q) => $q->where('is_active', 1)])
            ->search($request->query('q'))
            ->when($status === 'active', fn ($q) => $q->where('is_active', 1))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', 0))
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('companies.index', compact('companies', 'status'));
    }

    public function show(Company $company): View
    {
        return view('companies.show', [
            'company' => $company,
            'contacts' => $company->contacts()->orderByDesc('is_primary')->orderBy('full_name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('companies.form', ['company' => new Company(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $company = DB::transaction(function () use ($data) {
            // Insert with a temporary unique code, then set CMP### from the new id.
            $company = Company::create($data + ['company_code' => 'TMP'.Str::random(12), 'is_active' => true]);
            $company->update(['company_code' => sprintf('CMP%03d', $company->company_id)]);

            return $company;
        });

        // Back to the list, on the page where the new company sits (list is ordered by name),
        // with the row highlighted so it is obvious it was added.
        $before = Company::where('is_active', 1)->where('name', '<', $company->name)->count();
        $page = intdiv($before, self::PER_PAGE) + 1;

        return redirect()->route('companies.index', $page > 1 ? ['page' => $page] : [])
            ->with('status', "Company {$company->name} created ({$company->company_code}).")
            ->with('highlight', $company->company_id);
    }

    public function edit(Company $company): View
    {
        return view('companies.form', compact('company'));
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $company->update($this->validated($request, $company));

        return redirect()->route('companies.show', $company)->with('status', 'Company updated.');
    }

    public function toggleActive(Company $company): RedirectResponse
    {
        $company->update(['is_active' => ! $company->is_active]);

        return back()->with('status', $company->is_active ? 'Company activated.' : 'Company deactivated.');
    }

    private function validated(Request $request, ?Company $company = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150',
                Rule::unique('companies', 'name')->ignore($company?->company_id, 'company_id')],
            'industry' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:80'],
        ]);
    }
}
