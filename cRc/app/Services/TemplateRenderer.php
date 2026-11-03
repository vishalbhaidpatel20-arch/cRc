<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\MessageTemplate;
use App\Models\Ticket;
use App\Models\User;

/**
 * Fills {{placeholders}} in a message template. Pure text substitution: nothing is evaluated,
 * unknown or unavailable placeholders are left exactly as written so the agent sees what is missing.
 */
class TemplateRenderer
{
    private const PATTERN = '/\{\{\s*([A-Za-z_]+)\s*\}\}/';

    /** Single pass, so a value that itself contains "{{x}}" is never expanded again. */
    public function render(string $text, array $vars): string
    {
        return preg_replace_callback(self::PATTERN, function (array $m) use ($vars) {
            $value = $vars[$m[1]] ?? null;

            return $value === null || $value === '' ? $m[0] : (string) $value;
        }, $text);
    }

    /** @return array{subject: ?string, body: string} */
    public function renderTemplate(MessageTemplate $template, User $agent, ?Contact $contact, ?Ticket $ticket): array
    {
        $vars = $this->variables($agent, $contact, $ticket);

        return [
            'subject' => $template->subject === null ? null : $this->render($template->subject, $vars),
            'body' => $this->render($template->body, $vars),
        ];
    }

    /**
     * Everything the "log a conversation" panel needs: the usable templates and, when
     * ?template=ID is in the URL, that template already filled in for this contact/ticket.
     *
     * @return array{templates: \Illuminate\Support\Collection, selected: ?MessageTemplate, rendered: ?array}
     */
    public function panelData(\Illuminate\Http\Request $request, ?Contact $contact, ?Ticket $ticket): array
    {
        $templates = MessageTemplate::where('is_active', 1)->orderBy('name')->orderBy('channel')->get();
        $selected = $request->filled('template')
            ? $templates->firstWhere('template_id', (int) $request->query('template'))
            : null;

        return [
            'templates' => $templates,
            'selected' => $selected,
            'rendered' => $selected ? $this->renderTemplate($selected, $request->user(), $contact, $ticket) : null,
            // Every template already filled in for this contact/ticket, so the page can insert one
            // into the message box without a reload (id => ['subject' => ..., 'body' => ...]).
            'renderedAll' => $templates->mapWithKeys(
                fn ($t) => [$t->template_id => $this->renderTemplate($t, $request->user(), $contact, $ticket)]
            )->all(),
        ];
    }

    public function variables(User $agent, ?Contact $contact, ?Ticket $ticket): array
    {
        return [
            'name' => $contact?->full_name,
            'company' => $contact?->company?->name,
            'ticket_no' => $ticket?->ticket_no,
            'ticket_title' => $ticket?->title,
            'agent_name' => $agent->full_name,
        ];
    }

    /** Names of placeholders used in a text, e.g. ['name', 'ticket_no']. */
    public function placeholdersIn(string $text): array
    {
        preg_match_all(self::PATTERN, $text, $m);

        return array_values(array_unique($m[1]));
    }

    /** Placeholders in the text that are not in the allowed list. */
    public function unknownPlaceholders(string $text): array
    {
        return array_values(array_diff($this->placeholdersIn($text), array_keys(MessageTemplate::PLACEHOLDERS)));
    }
}
