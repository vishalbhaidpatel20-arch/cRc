<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * index/show: any logged-in user (agents may view every contact).
 * Everything else: admin only (see routes/web.php). Contacts are deactivated, never deleted.
 */
class ContactController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'active');

        $contacts = Contact::query()
            ->with('company')
            ->search($request->query('q'))
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->query('company_id')))
            ->when($status === 'active', fn ($q) => $q->where('contacts.is_active', 1))
            ->when($status === 'inactive', fn ($q) => $q->where('contacts.is_active', 0))
            ->orderBy('full_name')
            ->paginate(20)
            ->withQueryString();

        return view('contacts.index', [
            'contacts' => $contacts,
            'companies' => Company::orderBy('name')->get(['company_id', 'name']),
            'status' => $status,
        ]);
    }

    public function show(Contact $contact): View
    {
        return view('contacts.show', ['contact' => $contact->load('company')]);
    }

    public function create(Request $request): View
    {
        $contact = new Contact(['is_active' => true, 'company_id' => $request->query('company_id')]);

        return view('contacts.form', ['contact' => $contact] + $this->formData($contact));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $contact = DB::transaction(function () use ($data) {
            $contact = Contact::create($data + ['is_active' => true]);
            $this->enforceSinglePrimary($contact);

            return $contact;
        });

        return redirect()->route('contacts.show', $contact)->with('status', 'Contact created.');
    }

    public function edit(Contact $contact): View
    {
        return view('contacts.form', ['contact' => $contact] + $this->formData($contact));
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        $data = $this->validated($request, $contact);

        DB::transaction(function () use ($contact, $data) {
            $contact->update($data);
            $this->enforceSinglePrimary($contact);
        });

        return redirect()->route('contacts.show', $contact)->with('status', 'Contact updated.');
    }

    public function toggleActive(Contact $contact): RedirectResponse
    {
        $contact->update(['is_active' => ! $contact->is_active]);

        return back()->with('status', $contact->is_active ? 'Contact activated.' : 'Contact deactivated.');
    }

    /** A company has at most one primary contact: marking one clears the others. */
    private function enforceSinglePrimary(Contact $contact): void
    {
        if ($contact->is_primary) {
            Contact::where('company_id', $contact->company_id)
                ->where('contact_id', '!=', $contact->contact_id)
                ->update(['is_primary' => 0]);
        }
    }

    /** Only active companies can be chosen, plus the contact's current company when editing. */
    private function formData(Contact $contact): array
    {
        return [
            'companies' => Company::where('is_active', 1)
                ->orWhere('company_id', $contact->company_id)
                ->orderBy('name')->get(['company_id', 'name']),
        ];
    }

    private function validated(Request $request, ?Contact $contact = null): array
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', Rule::exists('companies', 'company_id')],
            'full_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190',
                Rule::unique('contacts', 'email')->ignore($contact?->contact_id, 'contact_id')],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp_no' => ['nullable', 'string', 'max:30'],
            'position' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'business_name' => ['nullable', 'string', 'max:150'],
            'product_role' => ['required', Rule::in(Contact::PRODUCT_ROLES)],
            'preferred_channel' => ['required', Rule::in(Contact::CHANNELS)],
        ]);

        $data['is_primary'] = $request->boolean('is_primary');

        return $data;
    }
}
