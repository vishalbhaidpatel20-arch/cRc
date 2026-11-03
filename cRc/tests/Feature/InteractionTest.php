<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Interaction;
use App\Models\MessageTemplate;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Milestone 4: the daily conversation log and message templates. */
class InteractionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['full_name' => 'Boss Admin']);
        $this->alice = User::factory()->agent()->create(['full_name' => 'Alice Agent']);
        $this->bob = User::factory()->agent()->create(['full_name' => 'Bob Agent']);
    }

    private function logPayload(array $over = []): array
    {
        return array_merge([
            'topic' => 'query', 'channel' => 'call', 'direction' => 'inbound',
            'summary' => 'Asked how to export the report.',
        ], $over);
    }

    // ---- logging from a ticket -------------------------------------------

    public function test_agent_logs_a_conversation_from_their_ticket(): void
    {
        $ticket = Ticket::factory()->assignedTo($this->alice)->create();

        $this->actingAs($this->alice)
            ->post(route('tickets.interactions.store', $ticket), $this->logPayload(['contact_id' => $ticket->contact_id]))
            ->assertRedirect();

        $this->assertDatabaseHas('interactions', [
            'contact_id' => $ticket->contact_id, 'user_id' => $this->alice->user_id,
            'ticket_id' => $ticket->ticket_id, 'channel' => 'call', 'direction' => 'inbound',
        ]);
        $this->get(route('tickets.show', $ticket))->assertSee('Asked how to export the report.');
    }

    public function test_logging_updates_the_contacts_last_contacted_date(): void
    {
        $ticket = Ticket::factory()->assignedTo($this->alice)->create();
        $this->assertNull($ticket->contact->last_contacted_at);

        $this->actingAs($this->alice)->post(route('tickets.interactions.store', $ticket), $this->logPayload([
            'contact_id' => $ticket->contact_id, 'interaction_at' => now()->subHours(3)->format('Y-m-d\TH:i'),
        ]));

        $this->assertNotNull($ticket->contact->fresh()->last_contacted_at);
    }

    public function test_agent_cannot_log_against_another_agents_ticket(): void
    {
        $bobsTicket = Ticket::factory()->assignedTo($this->bob)->create();
        $queueTicket = Ticket::factory()->unassigned()->create();
        $this->actingAs($this->alice);

        $this->post(route('tickets.interactions.store', $bobsTicket), $this->logPayload(['contact_id' => $bobsTicket->contact_id]))->assertNotFound();
        $this->post(route('tickets.interactions.store', $queueTicket), $this->logPayload(['contact_id' => $queueTicket->contact_id]))->assertNotFound();
        $this->assertDatabaseCount('interactions', 0);
    }

    public function test_admin_can_log_against_any_ticket(): void
    {
        $ticket = Ticket::factory()->assignedTo($this->bob)->create();

        $this->actingAs($this->admin)
            ->post(route('tickets.interactions.store', $ticket), $this->logPayload(['contact_id' => $ticket->contact_id]))
            ->assertRedirect();

        $this->assertDatabaseHas('interactions', ['ticket_id' => $ticket->ticket_id, 'user_id' => $this->admin->user_id]);
    }

    public function test_ticket_conversation_contact_must_belong_to_the_tickets_company(): void
    {
        $ticket = Ticket::factory()->assignedTo($this->alice)->create();
        $stranger = Contact::factory()->create();
        $sameCompanyInactive = Contact::factory()->inactive()->create(['company_id' => $ticket->company_id]);
        $sameCompany = Contact::factory()->create(['company_id' => $ticket->company_id]);
        $this->actingAs($this->alice);

        $this->post(route('tickets.interactions.store', $ticket), $this->logPayload(['contact_id' => $stranger->contact_id]))
            ->assertSessionHasErrors('contact_id');
        $this->post(route('tickets.interactions.store', $ticket), $this->logPayload(['contact_id' => $sameCompanyInactive->contact_id]))
            ->assertSessionHasErrors('contact_id');
        $this->assertDatabaseCount('interactions', 0);

        $this->post(route('tickets.interactions.store', $ticket), $this->logPayload(['contact_id' => $sameCompany->contact_id]))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('interactions', 1);
    }

    // ---- logging from a contact ------------------------------------------

    public function test_agent_logs_a_conversation_with_any_contact_without_a_ticket(): void
    {
        $contact = Contact::factory()->create();

        $this->actingAs($this->alice)
            ->post(route('contacts.interactions.store', $contact), $this->logPayload(['topic' => 'general']))
            ->assertRedirect(route('contacts.show', $contact).'#conversations');

        $this->assertDatabaseHas('interactions', ['contact_id' => $contact->contact_id, 'ticket_id' => null, 'user_id' => $this->alice->user_id]);
        $this->get(route('contacts.show', $contact))->assertSee('Asked how to export the report.');
    }

    public function test_agent_can_link_their_own_ticket_from_the_contact_page(): void
    {
        $ticket = Ticket::factory()->assignedTo($this->alice)->create();

        $this->actingAs($this->alice)
            ->post(route('contacts.interactions.store', $ticket->contact), $this->logPayload(['ticket_id' => $ticket->ticket_id]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('interactions', ['ticket_id' => $ticket->ticket_id]);
    }

    public function test_agent_cannot_link_a_ticket_that_is_not_theirs_from_the_contact_page(): void
    {
        $bobsTicket = Ticket::factory()->assignedTo($this->bob)->create();
        $queueTicket = Ticket::factory()->unassigned()->create(['company_id' => $bobsTicket->company_id, 'contact_id' => $bobsTicket->contact_id]);
        $this->actingAs($this->alice);

        $this->post(route('contacts.interactions.store', $bobsTicket->contact), $this->logPayload(['ticket_id' => $bobsTicket->ticket_id]))
            ->assertSessionHasErrors('ticket_id');
        $this->post(route('contacts.interactions.store', $bobsTicket->contact), $this->logPayload(['ticket_id' => $queueTicket->ticket_id]))
            ->assertSessionHasErrors('ticket_id');
        $this->assertDatabaseCount('interactions', 0);
    }

    public function test_ticket_must_belong_to_the_contacts_company(): void
    {
        $contact = Contact::factory()->create();
        $elsewhere = Ticket::factory()->assignedTo($this->alice)->create();

        $this->actingAs($this->alice)
            ->post(route('contacts.interactions.store', $contact), $this->logPayload(['ticket_id' => $elsewhere->ticket_id]))
            ->assertSessionHasErrors('ticket_id');
    }

    public function test_the_contact_page_only_offers_the_agents_own_tickets_to_link(): void
    {
        $contact = Contact::factory()->create();
        Ticket::factory()->assignedTo($this->alice)->create(['contact_id' => $contact->contact_id, 'company_id' => $contact->company_id, 'title' => 'Alice linkable']);
        Ticket::factory()->assignedTo($this->bob)->create(['contact_id' => $contact->contact_id, 'company_id' => $contact->company_id, 'title' => 'Bob hidden one']);

        $this->actingAs($this->alice)->get(route('contacts.show', $contact))
            ->assertSee('Alice linkable')->assertDontSee('Bob hidden one');
    }

    public function test_inactive_contacts_cannot_be_logged_against(): void
    {
        $contact = Contact::factory()->inactive()->create();

        $this->actingAs($this->alice)->post(route('contacts.interactions.store', $contact), $this->logPayload())
            ->assertSessionHasErrors('summary');
        $this->assertDatabaseCount('interactions', 0);
    }

    // ---- validation, integrity -------------------------------------------

    public function test_logging_is_validated(): void
    {
        $contact = Contact::factory()->create();
        $this->actingAs($this->alice);

        foreach ([
            ['summary' => ''], ['topic' => 'rant'], ['channel' => 'pigeon'], ['direction' => 'sideways'],
            ['interaction_at' => now()->addDay()->format('Y-m-d\TH:i')], ['interaction_at' => 'not a date'],
            ['summary' => str_repeat('x', 5001)],
        ] as $bad) {
            $this->post(route('contacts.interactions.store', $contact), $this->logPayload($bad))
                ->assertSessionHasErrors(array_key_first($bad));
        }

        $this->assertDatabaseCount('interactions', 0);
    }

    public function test_the_author_always_comes_from_the_session(): void
    {
        $contact = Contact::factory()->create();

        $this->actingAs($this->alice)->post(route('contacts.interactions.store', $contact), $this->logPayload([
            'user_id' => $this->bob->user_id, 'contact_id' => 99999,
        ]));

        $row = Interaction::firstOrFail();
        $this->assertSame($this->alice->user_id, $row->user_id);
        $this->assertSame($contact->contact_id, $row->contact_id);
    }

    public function test_the_log_is_append_only(): void
    {
        $i = Interaction::factory()->create();
        $this->actingAs($this->admin);

        $this->put("/interactions/{$i->interaction_id}", ['summary' => 'edited'])->assertStatus(404);
        $this->delete("/interactions/{$i->interaction_id}")->assertStatus(404);
        $this->assertDatabaseHas('interactions', ['interaction_id' => $i->interaction_id]);
    }

    public function test_summary_is_escaped_in_the_page(): void
    {
        $contact = Contact::factory()->create();
        $this->actingAs($this->alice)->post(route('contacts.interactions.store', $contact), $this->logPayload([
            'summary' => '<script>alert(1)</script>',
        ]));

        $this->get(route('contacts.show', $contact))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    // ---- reading the log -------------------------------------------------

    public function test_every_agent_can_read_the_whole_conversation_log(): void
    {
        Interaction::factory()->create(['user_id' => $this->bob->user_id, 'summary' => 'Bob talked about invoices']);
        Interaction::factory()->create(['user_id' => $this->alice->user_id, 'summary' => 'Alice talked about printers']);

        $this->actingAs($this->alice)->get(route('interactions.index'))
            ->assertOk()->assertSee('Bob talked about invoices')->assertSee('Alice talked about printers');
    }

    public function test_log_filters(): void
    {
        $contact = Contact::factory()->create(['full_name' => 'Findable Fiona']);
        Interaction::factory()->create(['user_id' => $this->alice->user_id, 'contact_id' => $contact->contact_id, 'channel' => 'whatsapp', 'topic' => 'bug', 'summary' => 'WA bug chat', 'interaction_at' => now()->subDays(2)]);
        Interaction::factory()->create(['user_id' => $this->bob->user_id, 'channel' => 'call', 'topic' => 'query', 'summary' => 'Phone query chat', 'interaction_at' => now()->subDays(10)]);
        $this->actingAs($this->admin);

        $this->get(route('interactions.index', ['channel' => 'whatsapp']))->assertSee('WA bug chat')->assertDontSee('Phone query chat');
        $this->get(route('interactions.index', ['topic' => 'query']))->assertSee('Phone query chat')->assertDontSee('WA bug chat');
        $this->get(route('interactions.index', ['user_id' => $this->bob->user_id]))->assertSee('Phone query chat')->assertDontSee('WA bug chat');
        $this->get(route('interactions.index', ['q' => 'Fiona']))->assertSee('WA bug chat')->assertDontSee('Phone query chat');
        $this->get(route('interactions.index', ['contact_id' => $contact->contact_id]))->assertSee('WA bug chat')->assertDontSee('Phone query chat');
        $this->get(route('interactions.index', ['from' => now()->subDays(3)->toDateString()]))->assertSee('WA bug chat')->assertDontSee('Phone query chat');
        $this->get(route('interactions.index', ['to' => now()->subDays(5)->toDateString()]))->assertSee('Phone query chat')->assertDontSee('WA bug chat');
        $this->get(route('interactions.index', ['from' => 'garbage']))->assertOk();
    }

    public function test_only_mine_filter_and_agents_cannot_filter_by_other_users(): void
    {
        Interaction::factory()->create(['user_id' => $this->alice->user_id, 'summary' => 'Mine alice entry']);
        Interaction::factory()->create(['user_id' => $this->bob->user_id, 'summary' => 'Bobs entry here']);
        $this->actingAs($this->alice);

        $this->get(route('interactions.index', ['mine' => 1]))->assertSee('Mine alice entry')->assertDontSee('Bobs entry here');
        // The agent filter is an admin tool; for agents it is ignored.
        $this->get(route('interactions.index', ['user_id' => $this->bob->user_id]))->assertSee('Mine alice entry')->assertSee('Bobs entry here');
    }

    public function test_log_hides_the_link_to_another_agents_ticket(): void
    {
        $bobsTicket = Ticket::factory()->assignedTo($this->bob)->create();
        Interaction::factory()->create(['ticket_id' => $bobsTicket->ticket_id, 'user_id' => $this->bob->user_id, 'contact_id' => $bobsTicket->contact_id, 'summary' => 'Chat about bob ticket']);

        $this->actingAs($this->alice)->get(route('interactions.index'))
            ->assertSee('Chat about bob ticket')->assertSee("another agent's ticket", false)
            ->assertDontSee($bobsTicket->ticket_no);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->bob)->get(route('interactions.index'))->assertSee($bobsTicket->ticket_no);
    }

    public function test_guests_cannot_log_or_read(): void
    {
        $contact = Contact::factory()->create();
        $this->get(route('interactions.index'))->assertRedirect(route('login'));
        $this->post(route('contacts.interactions.store', $contact), $this->logPayload())->assertRedirect(route('login'));
    }

    // ---- templates -------------------------------------------------------

    public function test_picking_a_template_fills_the_message_for_the_ticket_and_contact(): void
    {
        $ticket = Ticket::factory()->assignedTo($this->alice)->create(['title' => 'Printer jam']);
        $ticket->contact->update(['full_name' => 'Carla Client']);
        $tpl = MessageTemplate::factory()->create([
            'name' => 'Status update', 'subject' => 'Update on {{ticket_no}}',
            'body' => 'Hi {{name}} from {{company}}, {{agent_name}} here about {{ticket_title}}.',
        ]);

        $this->actingAs($this->alice)->get(route('tickets.show', ['ticket' => $ticket, 'template' => $tpl->template_id]))
            ->assertOk()
            ->assertSee('Hi Carla Client from '.e($ticket->company->name).', Alice Agent here about Printer jam.', false)
            ->assertSee('Update on '.$ticket->ticket_no);
    }

    public function test_template_on_the_contact_page_leaves_ticket_placeholders_visible(): void
    {
        $contact = Contact::factory()->create(['full_name' => 'Dev Contact']);
        $tpl = MessageTemplate::factory()->whatsapp()->create(['body' => 'Hi {{name}}, re {{ticket_no}}']);

        $this->actingAs($this->alice)->get(route('contacts.show', ['contact' => $contact, 'template' => $tpl->template_id]))
            ->assertSee('Hi Dev Contact, re {{ticket_no}}', false);
    }

    public function test_inactive_or_unknown_templates_are_ignored(): void
    {
        $contact = Contact::factory()->create();
        $off = MessageTemplate::factory()->inactive()->create(['body' => 'Secret inactive body']);

        $this->actingAs($this->alice);
        $this->get(route('contacts.show', ['contact' => $contact, 'template' => $off->template_id]))->assertOk()->assertDontSee('Secret inactive body');
        $this->get(route('contacts.show', ['contact' => $contact, 'template' => 99999]))->assertOk();
        $this->get(route('contacts.show', ['contact' => $contact, 'template' => 'abc']))->assertOk();
    }

    public function test_a_chosen_template_presets_channel_and_topic(): void
    {
        $contact = Contact::factory()->create();
        $tpl = MessageTemplate::factory()->whatsapp()->create(['category' => 'follow_up', 'body' => 'Just checking in']);

        $html = $this->actingAs($this->alice)->get(route('contacts.show', ['contact' => $contact, 'template' => $tpl->template_id]))->getContent();

        $this->assertMatchesRegularExpression('/<option value="whatsapp"\s+selected>/', $html);
        $this->assertMatchesRegularExpression('/<option value="follow_up"\s+selected>/', $html);
        $this->assertMatchesRegularExpression('/<option value="outbound"\s+selected>/', $html);
    }

    public function test_admin_manages_templates_and_agents_cannot(): void
    {
        $tpl = MessageTemplate::factory()->create(['name' => 'Existing']);
        $payload = ['name' => 'Fresh', 'category' => 'bug', 'channel' => 'email', 'subject' => 'Hello {{name}}', 'body' => 'Dear {{name}}'];

        $this->actingAs($this->alice);
        $this->get(route('templates.create'))->assertForbidden();
        $this->post(route('templates.store'), $payload)->assertForbidden();
        $this->get(route('templates.edit', $tpl))->assertForbidden();
        $this->put(route('templates.update', $tpl), $payload)->assertForbidden();
        $this->patch(route('templates.active', $tpl))->assertForbidden();
        $this->assertDatabaseMissing('message_templates', ['name' => 'Fresh']);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->admin);
        $this->post(route('templates.store'), $payload)->assertRedirect(route('templates.index'));
        $this->assertDatabaseHas('message_templates', ['name' => 'Fresh', 'is_active' => 1]);

        $this->put(route('templates.update', $tpl), array_merge($payload, ['name' => 'Renamed']))->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $tpl->fresh()->name);

        $this->patch(route('templates.active', $tpl));
        $this->assertFalse($tpl->fresh()->is_active);
        $this->delete("/templates/{$tpl->template_id}")->assertStatus(405);
    }

    public function test_template_validation(): void
    {
        MessageTemplate::factory()->create(['name' => 'Taken', 'channel' => 'email']);
        $ok = ['name' => 'New one', 'category' => 'general', 'channel' => 'email', 'subject' => 'Subj', 'body' => 'Body {{name}}'];
        $this->actingAs($this->admin);

        $this->post(route('templates.store'), ['name' => 'Taken'] + $ok)->assertSessionHasErrors('name');
        // The same name is fine on the other channel.
        $this->post(route('templates.store'), ['name' => 'Taken', 'channel' => 'whatsapp', 'subject' => null] + $ok)->assertSessionHasNoErrors();
        $this->post(route('templates.store'), ['subject' => ''] + $ok)->assertSessionHasErrors('subject');
        $this->post(route('templates.store'), ['category' => 'rant'] + $ok)->assertSessionHasErrors('category');
        $this->post(route('templates.store'), ['channel' => 'sms'] + $ok)->assertSessionHasErrors('channel');
        $this->post(route('templates.store'), ['body' => ''] + $ok)->assertSessionHasErrors('body');
        $this->post(route('templates.store'), ['body' => 'Hi {{nickname}} and {{ ticket_no }}'] + $ok)->assertSessionHasErrors('body');
        $this->post(route('templates.store'), ['subject' => 'Re {{secret}}'] + $ok)->assertSessionHasErrors('body');
    }

    public function test_whatsapp_templates_drop_the_subject(): void
    {
        $this->actingAs($this->admin)->post(route('templates.store'), [
            'name' => 'WA note', 'category' => 'general', 'channel' => 'whatsapp', 'subject' => 'ignored', 'body' => 'Hi {{name}}',
        ]);

        $this->assertNull(MessageTemplate::where('name', 'WA note')->firstOrFail()->subject);
    }

    public function test_agents_see_only_active_templates_admins_can_see_inactive(): void
    {
        MessageTemplate::factory()->create(['name' => 'Live template']);
        MessageTemplate::factory()->inactive()->create(['name' => 'Retired template']);

        $this->actingAs($this->alice)->get(route('templates.index', ['status' => 'all']))
            ->assertSee('Live template')->assertDontSee('Retired template');

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->admin)->get(route('templates.index', ['status' => 'all']))
            ->assertSee('Live template')->assertSee('Retired template');
    }
}
