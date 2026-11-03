<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contact;
use App\Models\ContactProfile;
use App\Models\ProductModule;
use App\Models\Interaction;
use App\Models\Ticket;
use App\Services\TemplateRenderer;
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

    public function show(Request $request, Contact $contact, TemplateRenderer $renderer): View
    {
        $contact->load(['company', 'profile', 'modules']);

        return view('contacts.show', $renderer->panelData($request, $contact, null) + [
            'contact' => $contact,
            'interactionCount' => Interaction::where('contact_id', $contact->contact_id)->count(),
            'interactions' => Interaction::with(['user', 'ticket', 'followUp'])->where('contact_id', $contact->contact_id)
                ->orderByDesc('interaction_at')->orderByDesc('interaction_id')->limit(15)->get(),
            // Goes through the Ticket global scope: an agent can only link their own tickets.
            'linkableTickets' => Ticket::open()->where('company_id', $contact->company_id)
                ->orderBy('tickets.created_at')->get(['ticket_id', 'ticket_no', 'title']),
        ]);
    }

    public function create(Request $request): View
    {
        $contact = new Contact(['is_active' => true, 'company_id' => $request->query('company_id')]);

        return view('contacts.form', ['contact' => $contact] + $this->formData($contact));
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $moduleIds, $preferredTime] = $this->split($this->validated($request));

        $contact = DB::transaction(function () use ($data, $moduleIds, $preferredTime) {
            $contact = Contact::create($data + ['is_active' => true]);
            $this->enforceSinglePrimary($contact);
            $this->saveExtras($contact, $moduleIds, $preferredTime);

            return $contact;
        });

        return redirect()->route('contacts.show', $contact)->with('status', 'Contact created.');
    }

    public function edit(Contact $contact): View
    {
        $contact->load(['profile', 'modules']);

        return view('contacts.form', ['contact' => $contact] + $this->formData($contact));
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        [$data, $moduleIds, $preferredTime] = $this->split($this->validated($request, $contact));

        DB::transaction(function () use ($contact, $data, $moduleIds, $preferredTime) {
            $contact->update($data);
            $this->enforceSinglePrimary($contact);
            $this->saveExtras($contact, $moduleIds, $preferredTime);
        });

        return redirect()->route('contacts.show', $contact)->with('status', 'Contact updated.');
    }

    public function toggleActive(Contact $contact): RedirectResponse
    {
        $contact->update(['is_active' => ! $contact->is_active]);

        return back()->with('status', $contact->is_active ? 'Contact activated.' : 'Contact deactivated.');
    }

    /** Separates the contact columns from the modules and the profile's preferred time. */
    private function split(array $data): array
    {
        $modules = array_map('intval', $data['modules'] ?? []);
        $time = $data['preferred_time'] ?? null;
        unset($data['modules'], $data['preferred_time']);

        return [$data, $modules, $time];
    }

    private function saveExtras(Contact $contact, array $moduleIds, ?string $preferredTime): void
    {
        $contact->modules()->sync($moduleIds);

        if ($preferredTime !== null && $preferredTime !== '') {
            ContactProfile::updateOrCreate(['contact_id' => $contact->contact_id], ['preferred_time' => $preferredTime]);
        } else {
            ContactProfile::where('contact_id', $contact->contact_id)->update(['preferred_time' => null]);
        }
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
            'allModules' => ProductModule::orderBy('name')->get(['module_id', 'name']),
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
            'preferred_time' => ['nullable', 'string', 'max:40'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['integer', Rule::exists('product_modules', 'module_id')],
        ]);

        $data['is_primary'] = $request->boolean('is_primary');

        return $data;
    }
}
