<?php

namespace App\Http\Controllers;

use App\Models\MessageTemplate;
use App\Services\TemplateRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * index: every logged-in user (agents see active templates only). Everything else: admin only.
 * Templates are deactivated, never deleted. Nothing is ever sent; agents copy the filled-in text.
 */
class TemplateController extends Controller
{
    public function __construct(private TemplateRenderer $renderer) {}

    public function index(Request $request): View
    {
        $isAdmin = $request->user()->isAdmin();
        $status = $isAdmin ? $request->query('status', 'active') : 'active';

        $templates = MessageTemplate::query()
            ->when(in_array($request->query('channel'), MessageTemplate::CHANNELS, true), fn ($q) => $q->where('channel', $request->query('channel')))
            ->when(in_array($request->query('category'), MessageTemplate::CATEGORIES, true), fn ($q) => $q->where('category', $request->query('category')))
            ->when($status === 'active', fn ($q) => $q->where('is_active', 1))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', 0))
            ->orderBy('category')->orderBy('name')->orderBy('channel')
            ->get();

        return view('templates.index', compact('templates', 'status'));
    }

    public function create(): View
    {
        return view('templates.form', ['template' => new MessageTemplate(['category' => 'general', 'channel' => 'email', 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $template = MessageTemplate::create($this->validated($request) + ['is_active' => true]);

        return redirect()->route('templates.index')
            ->with('status', "Template {$template->name} created.")
            ->with('highlight', $template->template_id);
    }

    public function edit(MessageTemplate $template): View
    {
        return view('templates.form', compact('template'));
    }

    public function update(Request $request, MessageTemplate $template): RedirectResponse
    {
        $template->update($this->validated($request, $template));

        return redirect()->route('templates.index')
            ->with('status', 'Template updated.')
            ->with('highlight', $template->template_id);
    }

    public function toggleActive(MessageTemplate $template): RedirectResponse
    {
        $template->update(['is_active' => ! $template->is_active]);

        return back()->with('status', $template->is_active ? 'Template activated.' : 'Template deactivated.');
    }

    private function validated(Request $request, ?MessageTemplate $template = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120',
                // The same name may exist once per channel (email + whatsapp).
                Rule::unique('message_templates', 'name')
                    ->where('channel', $request->input('channel'))
                    ->ignore($template?->template_id, 'template_id')],
            'category' => ['required', Rule::in(MessageTemplate::CATEGORIES)],
            'channel' => ['required', Rule::in(MessageTemplate::CHANNELS)],
            'subject' => ['nullable', 'string', 'max:200', 'required_if:channel,email'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        if ($data['channel'] === 'whatsapp') {
            $data['subject'] = null;   // WhatsApp messages have no subject line
        }

        $unknown = $this->renderer->unknownPlaceholders(($data['subject'] ?? '')."\n".$data['body']);
        if ($unknown !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'body' => 'Unknown placeholder(s): '.implode(', ', array_map(fn ($p) => '{{'.$p.'}}', $unknown))
                    .'. Allowed: '.implode(', ', array_map(fn ($p) => '{{'.$p.'}}', array_keys(MessageTemplate::PLACEHOLDERS))).'.',
            ]);
        }

        return $data;
    }
}
