<?php

namespace Tests\Unit\Services;

use App\Models\Company;
use App\Models\Contact;
use App\Models\MessageTemplate;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateRendererTest extends TestCase
{
    use RefreshDatabase;

    private TemplateRenderer $r;

    protected function setUp(): void
    {
        parent::setUp();
        $this->r = new TemplateRenderer;
    }

    public function test_it_replaces_known_placeholders(): void
    {
        $this->assertSame('Hi Asha from Initech', $this->r->render('Hi {{name}} from {{company}}', ['name' => 'Asha', 'company' => 'Initech']));
    }

    public function test_whitespace_inside_the_braces_is_tolerated(): void
    {
        $this->assertSame('Hi Asha', $this->r->render('Hi {{ name }}', ['name' => 'Asha']));
        $this->assertSame('Hi Asha', $this->r->render('Hi {{   name}}', ['name' => 'Asha']));
    }

    public function test_the_same_placeholder_can_repeat(): void
    {
        $this->assertSame('Asha, Asha', $this->r->render('{{name}}, {{name}}', ['name' => 'Asha']));
    }

    public function test_unknown_and_unavailable_placeholders_are_left_as_written(): void
    {
        $this->assertSame('Re {{ticket_no}} {{nickname}}', $this->r->render('Re {{ticket_no}} {{nickname}}', ['ticket_no' => null, 'name' => 'x']));
        $this->assertSame('Re {{ticket_no}}', $this->r->render('Re {{ticket_no}}', ['ticket_no' => '']));
    }

    public function test_values_are_inserted_literally_and_never_expanded_again(): void
    {
        $out = $this->r->render('Hi {{name}}', ['name' => '{{agent_name}}', 'agent_name' => 'Boss']);
        $this->assertSame('Hi {{agent_name}}', $out);
    }

    public function test_it_does_not_evaluate_anything(): void
    {
        $out = $this->r->render('{{name}} {{ system("x") }} <?php echo 1; ?> {!! 1+1 !!}', ['name' => '<b>x</b>']);
        $this->assertSame('<b>x</b> {{ system("x") }} <?php echo 1; ?> {!! 1+1 !!}', $out);
    }

    public function test_text_without_placeholders_is_unchanged(): void
    {
        $this->assertSame("Plain {text}\nwith { braces }", $this->r->render("Plain {text}\nwith { braces }", ['name' => 'x']));
    }

    public function test_placeholders_in_lists_names_once(): void
    {
        $this->assertSame(['name', 'ticket_no'], $this->r->placeholdersIn('{{name}} {{ ticket_no }} {{name}}'));
        $this->assertSame([], $this->r->placeholdersIn('nothing here'));
    }

    public function test_unknown_placeholders_are_found_against_the_allowed_list(): void
    {
        $this->assertSame(['nickname'], $this->r->unknownPlaceholders('{{name}} {{nickname}}'));
        $this->assertSame([], $this->r->unknownPlaceholders('{{name}} {{company}} {{ticket_no}} {{ticket_title}} {{agent_name}}'));
    }

    public function test_the_allowed_list_is_exactly_the_documented_set(): void
    {
        $this->assertSame(['name', 'company', 'ticket_no', 'ticket_title', 'agent_name'], array_keys(MessageTemplate::PLACEHOLDERS));
        $this->assertSame('{{name}}', MessageTemplate::token('name'));
    }

    public function test_variables_come_from_the_contact_ticket_and_agent(): void
    {
        $company = Company::factory()->create(['name' => 'Initech']);
        $contact = Contact::factory()->for($company)->create(['full_name' => 'Peter G']);
        $ticket = Ticket::factory()->create(['contact_id' => $contact->contact_id, 'title' => 'Jam', 'ticket_no' => 'TKT-0042']);
        $agent = User::factory()->agent()->create(['full_name' => 'Anna']);

        $this->assertSame(
            ['name' => 'Peter G', 'company' => 'Initech', 'ticket_no' => 'TKT-0042', 'ticket_title' => 'Jam', 'agent_name' => 'Anna'],
            $this->r->variables($agent, $contact->load('company'), $ticket)
        );
        $this->assertSame(
            ['name' => null, 'company' => null, 'ticket_no' => null, 'ticket_title' => null, 'agent_name' => 'Anna'],
            $this->r->variables($agent, null, null)
        );
    }

    public function test_render_template_fills_subject_and_body_and_keeps_a_null_subject(): void
    {
        $contact = Contact::factory()->create(['full_name' => 'Peter G'])->load('company');
        $agent = User::factory()->agent()->create(['full_name' => 'Anna']);

        $email = MessageTemplate::factory()->create(['subject' => 'Hello {{name}}', 'body' => 'From {{agent_name}}']);
        $wa = MessageTemplate::factory()->whatsapp()->create(['body' => 'Hi {{name}}']);

        $this->assertSame(['subject' => 'Hello Peter G', 'body' => 'From Anna'], $this->r->renderTemplate($email, $agent, $contact, null));
        $this->assertSame(['subject' => null, 'body' => 'Hi Peter G'], $this->r->renderTemplate($wa, $agent, $contact, null));
    }
}
