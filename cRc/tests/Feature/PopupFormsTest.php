<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\MessageTemplate;
use App\Models\ProductModule;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every "create" (and the edit buttons) opens a pop-up on the page you are already on, and a
 * successful save lands back on the list with the new row highlighted.
 */
class PopupFormsTest extends TestCase
{
    use RefreshDatabase;

    private const REOPENS = '/open\(\);\s*\/\/ the pop-up was submitted/';

    private User $admin;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->agent = User::factory()->agent()->create(['full_name' => 'Pop Agent']);
    }

    private function reopened(string $html): int
    {
        return preg_match_all(self::REOPENS, $html);
    }

    // ---- new agent -------------------------------------------------------

    public function test_users_page_has_the_new_agent_popup(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk()
            ->assertSee('<dialog id="user-modal"', false)
            ->assertSee('id="new-user-link"', false)->assertSee('data-modal="user-modal"', false)
            ->assertSee('action="'.route('admin.users.store').'"', false)
            ->assertSee('name="_modal" value="user"', false)
            ->assertSee('name="full_name"', false)->assertSee('name="password_confirmation"', false)
            ->assertSee('href="'.route('admin.users.create').'"', false);
    }

    public function test_creating_an_agent_returns_to_the_list_with_the_row_highlighted(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'full_name' => 'Brand New', 'email' => 'brand.new@example.com', 'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1', 'all_companies' => '1', '_modal' => 'user',
        ]);

        $agent = User::where('email', 'brand.new@example.com')->firstOrFail();
        $response->assertRedirect(route('admin.users.index'))->assertSessionHas('highlight', $agent->user_id);

        $this->get(route('admin.users.index'))->assertSee('Brand New')->assertSee('class="row-new"', false)
            ->assertSee('Agent Brand New created.');
    }

    public function test_a_failed_new_agent_submit_reopens_the_popup_with_input_and_errors(): void
    {
        $this->actingAs($this->admin)->from(route('admin.users.index'))->post(route('admin.users.store'), [
            'full_name' => 'Half Done', 'email' => 'half@example.com', 'password' => 'short',
            'password_confirmation' => 'different', 'all_companies' => '1', '_modal' => 'user',
        ])->assertRedirect(route('admin.users.index'));

        $html = $this->get(route('admin.users.index'))->getContent();

        $this->assertSame(1, $this->reopened($html));
        $this->assertStringContainsString('value="Half Done"', $html);
        $this->assertStringContainsString('value="half@example.com"', $html);
        $this->assertStringContainsString('class="error"', $html);
        $this->assertDatabaseMissing('users', ['email' => 'half@example.com']);
    }

    public function test_agents_cannot_open_or_post_to_the_user_popup(): void
    {
        $this->actingAs($this->agent)->get(route('admin.users.index'))->assertForbidden();
        $this->post(route('admin.users.store'), ['full_name' => 'X', 'email' => 'x@example.com', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1', 'all_companies' => '1', '_modal' => 'user'])
            ->assertForbidden();
    }

    // ---- new contact -----------------------------------------------------

    public function test_contacts_page_has_the_new_contact_popup_for_admins_only(): void
    {
        Company::factory()->create(['name' => 'Pop Company']);
        ProductModule::create(['name' => 'Module 1']);

        $this->actingAs($this->admin)->get(route('contacts.index'))->assertOk()
            ->assertSee('<dialog id="contact-modal"', false)->assertSee('name="_modal" value="contact"', false)
            ->assertSee('data-modal="contact-modal"', false)
            ->assertSee('name="company_id"', false)->assertSee('Pop Company')->assertSee('name="modules[]"', false)
            ->assertSee('name="preferred_time"', false)->assertSee('name="is_primary"', false);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->agent)->get(route('contacts.index'))->assertOk()
            ->assertDontSee('contact-modal')->assertDontSee('+ New contact');
    }

    public function test_the_contact_popup_only_offers_active_companies(): void
    {
        Company::factory()->create(['name' => 'Live Company']);
        Company::factory()->inactive()->create(['name' => 'Dormant Company']);

        $html = $this->actingAs($this->admin)->get(route('contacts.index'))->getContent();
        $modal = substr($html, strpos($html, '<dialog id="contact-modal"'));

        $this->assertStringContainsString('Live Company', $modal);
        $this->assertStringNotContainsString('Dormant Company', $modal);
    }

    public function test_creating_a_contact_returns_to_the_list_highlighted_with_modules_saved(): void
    {
        $company = Company::factory()->create();
        $module = ProductModule::create(['name' => 'Module 1']);

        $response = $this->actingAs($this->admin)->post(route('contacts.store'), [
            'company_id' => $company->company_id, 'full_name' => 'Pop Person', 'email' => 'pop.person@example.com',
            'product_role' => 'end_user', 'preferred_channel' => 'call', 'modules' => [$module->module_id], '_modal' => 'contact',
        ]);

        $contact = Contact::where('email', 'pop.person@example.com')->firstOrFail();
        $response->assertRedirect(route('contacts.index'))->assertSessionHas('highlight', $contact->contact_id);
        $this->assertSame(1, $contact->modules()->count());

        $this->get(route('contacts.index'))->assertSee('Pop Person')->assertSee('class="row-new"', false)
            ->assertSee('Contact Pop Person created.');
    }

    public function test_a_new_contact_that_sorts_late_is_shown_on_its_page(): void
    {
        $company = Company::factory()->create();
        foreach (range(1, 25) as $i) {
            Contact::factory()->for($company)->create(['full_name' => sprintf('Alpha %02d', $i)]);
        }

        $this->actingAs($this->admin)->post(route('contacts.store'), [
            'company_id' => $company->company_id, 'full_name' => 'Zulu Last', 'email' => 'zulu@example.com',
            'product_role' => 'end_user', 'preferred_channel' => 'email',
        ])->assertRedirect(route('contacts.index', ['page' => 2]));

        $this->get(route('contacts.index', ['page' => 2]))->assertSee('Zulu Last')->assertSee('class="row-new"', false);
    }

    public function test_a_failed_new_contact_submit_reopens_the_popup(): void
    {
        $company = Company::factory()->create();
        Contact::factory()->for($company)->create(['email' => 'taken@example.com']);

        $this->actingAs($this->admin)->from(route('contacts.index'))->post(route('contacts.store'), [
            'company_id' => $company->company_id, 'full_name' => 'Typed Name', 'email' => 'taken@example.com',
            'product_role' => 'end_user', 'preferred_channel' => 'call', '_modal' => 'contact',
        ])->assertRedirect(route('contacts.index'));

        $html = $this->get(route('contacts.index'))->getContent();

        $this->assertSame(1, $this->reopened($html));
        $this->assertStringContainsString('value="Typed Name"', $html);
        $this->assertStringContainsString('has already been taken', $html);
    }

    public function test_add_contact_link_on_a_company_opens_the_popup_with_that_company_selected(): void
    {
        $company = Company::factory()->create(['name' => 'Preselected Co']);
        Company::factory()->create(['name' => 'Other Co']);

        $this->actingAs($this->admin)->get(route('companies.show', $company))
            ->assertSee(e(route('contacts.index', ['new' => 1, 'company_id' => $company->company_id])), false);

        $html = $this->get(route('contacts.index', ['new' => 1, 'company_id' => $company->company_id]))->getContent();

        $this->assertSame(1, $this->reopened($html), 'the pop-up opens by itself');
        $this->assertMatchesRegularExpression('/<option value="'.$company->company_id.'"\s+selected>Preselected Co/', substr($html, strpos($html, '<dialog id="contact-modal"')));
    }

    public function test_the_contact_popup_does_not_open_on_a_plain_visit(): void
    {
        $this->assertSame(0, $this->reopened($this->actingAs($this->admin)->get(route('contacts.index'))->getContent()));
    }

    // ---- new ticket ------------------------------------------------------

    public function test_tickets_page_has_the_new_ticket_popup_for_admins_and_agents(): void
    {
        $contact = Contact::factory()->create(['full_name' => 'Pickable Person']);

        $this->actingAs($this->admin)->get(route('tickets.index'))->assertOk()
            ->assertSee('<dialog id="ticket-modal"', false)->assertSee('name="_modal" value="ticket"', false)
            ->assertSee('data-modal="ticket-modal"', false)->assertSee('Pickable Person')->assertSee('name="assigned_to"', false);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->agent)->get(route('tickets.index'))->assertOk()
            ->assertSee('<dialog id="ticket-modal"', false)->assertSee('will be assigned to you')->assertDontSee('name="assigned_to"', false);
    }

    public function test_creating_a_ticket_returns_to_the_list_highlighted(): void
    {
        $contact = Contact::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('tickets.store'), [
            'contact_id' => $contact->contact_id, 'topic' => 'bug', 'priority' => 'high', 'title' => 'Pop-up ticket', '_modal' => 'ticket',
        ]);

        $ticket = Ticket::firstOrFail();
        $response->assertRedirect(route('tickets.index'))->assertSessionHas('highlight', $ticket->ticket_id);
        $this->get(route('tickets.index'))->assertSee('Pop-up ticket')->assertSee('class="row-new"', false)
            ->assertSee("Ticket {$ticket->ticket_no} created.");
    }

    public function test_an_agent_creating_a_ticket_sees_it_on_their_list(): void
    {
        $contact = Contact::factory()->create();

        $this->actingAs($this->agent)->post(route('tickets.store'), [
            'contact_id' => $contact->contact_id, 'topic' => 'query', 'priority' => 'low', 'title' => 'Agent pop-up ticket', '_modal' => 'ticket',
        ])->assertRedirect(route('tickets.index'));

        $this->get(route('tickets.index'))->assertSee('Agent pop-up ticket')->assertSee('class="row-new"', false);
    }

    public function test_a_new_ticket_lands_on_the_page_where_it_sits_in_priority_order(): void
    {
        $contact = Contact::factory()->create();
        Ticket::factory()->count(25)->unassigned()->priority('critical')->create();

        // 25 criticals come first: a new LOW ticket belongs on page 2.
        $this->actingAs($this->admin)->post(route('tickets.store'), [
            'contact_id' => $contact->contact_id, 'topic' => 'query', 'priority' => 'low', 'title' => 'Low and late',
        ])->assertRedirect(route('tickets.index', ['page' => 2]));
        $this->get(route('tickets.index', ['page' => 2]))->assertSee('Low and late')->assertSee('class="row-new"', false);

        // A new CRITICAL ticket is the newest critical, so it sorts after the 25 older ones: page 2.
        $this->post(route('tickets.store'), [
            'contact_id' => $contact->contact_id, 'topic' => 'bug', 'priority' => 'critical', 'title' => 'Newest critical',
        ])->assertRedirect(route('tickets.index', ['page' => 2]));
    }

    public function test_a_new_ticket_on_a_short_list_stays_on_page_one(): void
    {
        Ticket::factory()->count(3)->unassigned()->create();

        $this->actingAs($this->admin)->post(route('tickets.store'), [
            'contact_id' => Contact::factory()->create()->contact_id, 'topic' => 'query', 'priority' => 'medium', 'title' => 'Short list',
        ])->assertRedirect(route('tickets.index'));
    }

    public function test_the_page_count_ignores_resolved_tickets_and_other_agents_tickets_for_an_agent(): void
    {
        $other = User::factory()->agent()->create();
        Ticket::factory()->count(30)->assignedTo($other)->priority('critical')->create();   // not visible to $agent
        Ticket::factory()->count(30)->assignedTo($this->agent)->priority('critical')->resolved()->create();   // resolved: not in the list

        $this->actingAs($this->agent)->post(route('tickets.store'), [
            'contact_id' => Contact::factory()->create()->contact_id, 'topic' => 'query', 'priority' => 'low', 'title' => 'Mine alone',
        ])->assertRedirect(route('tickets.index'));
    }

    public function test_a_failed_new_ticket_submit_reopens_the_popup(): void
    {
        $this->actingAs($this->admin)->from(route('tickets.index'))->post(route('tickets.store'), [
            'contact_id' => '', 'topic' => 'query', 'priority' => 'low', 'title' => 'No contact chosen', '_modal' => 'ticket',
        ])->assertRedirect(route('tickets.index'));

        $html = $this->get(route('tickets.index'))->getContent();

        $this->assertSame(1, $this->reopened($html));
        $this->assertStringContainsString('value="No contact chosen"', $html);
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_new_ticket_links_open_the_popup_with_the_contact_selected(): void
    {
        $contact = Contact::factory()->create(['full_name' => 'Linked Person']);

        $this->actingAs($this->agent)->get(route('contacts.show', $contact))
            ->assertSee(e(route('tickets.index', ['new' => 1, 'contact_id' => $contact->contact_id])), false);
        $this->get(route('agent.dashboard'))->assertSee(route('tickets.index', ['new' => 1]), false);

        $html = $this->get(route('tickets.index', ['new' => 1, 'contact_id' => $contact->contact_id]))->getContent();
        $this->assertSame(1, $this->reopened($html));
        $this->assertMatchesRegularExpression('/<option value="'.$contact->contact_id.'"\s+selected>Linked Person/', $html);
    }

    // ---- new / edit template ---------------------------------------------

    public function test_templates_page_has_a_create_popup_and_an_edit_popup_per_template(): void
    {
        $a = MessageTemplate::factory()->create(['name' => 'Alpha tpl']);
        $b = MessageTemplate::factory()->create(['name' => 'Beta tpl']);

        $this->actingAs($this->admin)->get(route('templates.index'))->assertOk()
            ->assertSee('<dialog id="template-modal"', false)->assertSee('data-modal="template-modal"', false)
            ->assertSee('<dialog id="template-edit-'.$a->template_id.'"', false)
            ->assertSee('<dialog id="template-edit-'.$b->template_id.'"', false)
            ->assertSee('data-modal="template-edit-'.$a->template_id.'"', false);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->agent)->get(route('templates.index'))->assertOk()
            ->assertDontSee('<dialog', false)->assertDontSee('+ New template');
    }

    public function test_creating_a_template_returns_to_the_list_highlighted(): void
    {
        $response = $this->actingAs($this->admin)->post(route('templates.store'), [
            'name' => 'Pop template', 'category' => 'bug', 'channel' => 'email', 'subject' => 'Hi {{name}}', 'body' => 'Dear {{name}}', '_modal' => 'template-new',
        ]);

        $tpl = MessageTemplate::where('name', 'Pop template')->firstOrFail();
        $response->assertRedirect(route('templates.index'))->assertSessionHas('highlight', $tpl->template_id);
        $this->get(route('templates.index'))->assertSee('Pop template')->assertSee('class="row-new"', false)->assertSee('Template Pop template created.');
    }

    public function test_editing_a_template_from_its_popup_saves_and_highlights_it(): void
    {
        $tpl = MessageTemplate::factory()->create(['name' => 'Old name']);

        $this->actingAs($this->admin)->put(route('templates.update', $tpl), [
            'name' => 'New name', 'category' => 'general', 'channel' => 'email', 'subject' => 'S', 'body' => 'B {{name}}', '_modal' => 'template-edit-'.$tpl->template_id,
        ])->assertRedirect(route('templates.index'))->assertSessionHas('highlight', $tpl->template_id);

        $this->assertSame('New name', $tpl->fresh()->name);
    }

    public function test_a_failed_template_edit_reopens_only_that_templates_popup_with_its_own_input(): void
    {
        $a = MessageTemplate::factory()->create(['name' => 'Alpha tpl', 'body' => 'Alpha body {{name}}']);
        $b = MessageTemplate::factory()->create(['name' => 'Beta tpl', 'body' => 'Beta body {{name}}']);

        $this->actingAs($this->admin)->from(route('templates.index'))->put(route('templates.update', $b), [
            'name' => 'Edited beta', 'category' => 'general', 'channel' => 'email', 'subject' => 'S', 'body' => 'Bad {{nickname}}',
            '_modal' => 'template-edit-'.$b->template_id,
        ])->assertRedirect(route('templates.index'));

        $html = $this->get(route('templates.index'))->getContent();

        $this->assertSame(1, $this->reopened($html), 'exactly one pop-up reopens');
        $this->assertStringContainsString('Unknown placeholder', $html);
        $this->assertSame(1, substr_count($html, 'Unknown placeholder(s)'), 'the error shows only in the submitted pop-up');
        $this->assertStringContainsString('value="Edited beta"', $html);
        $this->assertStringContainsString('Alpha body {{name}}', $html, "Alpha's pop-up keeps its own text");
        $this->assertSame('Beta tpl', $b->fresh()->name);
    }

    public function test_a_failed_new_template_does_not_leak_into_the_edit_popups(): void
    {
        $a = MessageTemplate::factory()->create(['name' => 'Alpha tpl']);

        $this->actingAs($this->admin)->from(route('templates.index'))->post(route('templates.store'), [
            'name' => 'Typed new one', 'category' => 'general', 'channel' => 'email', 'subject' => 'S', 'body' => 'Bad {{oops}}', '_modal' => 'template-new',
        ]);

        $html = $this->get(route('templates.index'))->getContent();
        $edit = substr($html, strpos($html, '<dialog id="template-edit-'.$a->template_id));

        $this->assertStringContainsString('value="Typed new one"', $html);
        $this->assertStringNotContainsString('Typed new one', $edit, "the edit pop-up must not pick up the new template's typed values");
        $this->assertStringContainsString('value="Alpha tpl"', $edit);
    }

    public function test_the_stand_alone_template_pages_still_work(): void
    {
        $tpl = MessageTemplate::factory()->create(['name' => 'Standalone tpl']);
        $this->actingAs($this->admin);

        $this->get(route('templates.create'))->assertOk()->assertSee('New template');
        $this->get(route('templates.edit', $tpl))->assertOk()->assertSee('Standalone tpl');
    }

    // ---- edit pop-ups on the detail pages --------------------------------

    public function test_company_page_edits_in_a_popup_and_stays_on_the_page(): void
    {
        $company = Company::factory()->create(['name' => 'Edit Me Co', 'city' => 'Old City']);

        $this->actingAs($this->admin)->get(route('companies.show', $company))
            ->assertSee('<dialog id="company-edit-modal"', false)->assertSee('data-modal="company-edit-modal"', false)
            ->assertSee('action="'.route('companies.update', $company).'"', false)->assertSee('value="Old City"', false);

        $this->put(route('companies.update', $company), ['name' => 'Edited Co', 'city' => 'New City', '_modal' => 'company-edit'])
            ->assertRedirect(route('companies.show', $company));
        $this->get(route('companies.show', $company))->assertSee('Edited Co')->assertSee('New City');
    }

    public function test_a_failed_company_edit_reopens_its_popup(): void
    {
        Company::factory()->create(['name' => 'Taken']);
        $company = Company::factory()->create(['name' => 'Mine']);

        $this->actingAs($this->admin)->from(route('companies.show', $company))
            ->put(route('companies.update', $company), ['name' => 'Taken', '_modal' => 'company-edit'])
            ->assertRedirect(route('companies.show', $company));

        $html = $this->get(route('companies.show', $company))->getContent();
        $this->assertSame(1, $this->reopened($html));
        $this->assertStringContainsString('has already been taken', $html);
        $this->assertSame('Mine', $company->fresh()->name);
    }

    public function test_company_page_has_no_edit_popup_for_agents(): void
    {
        $this->actingAs($this->agent)->get(route('companies.show', Company::factory()->create()))
            ->assertOk()->assertDontSee('company-edit-modal')->assertDontSee('<dialog', false);
    }

    public function test_contact_page_edits_in_a_popup_with_modules_and_stays_on_the_page(): void
    {
        $contact = Contact::factory()->create(['full_name' => 'Edit Me']);
        $m = ProductModule::create(['name' => 'Module 1']);

        $this->actingAs($this->admin)->get(route('contacts.show', $contact))
            ->assertSee('<dialog id="contact-edit-modal"', false)->assertSee('data-modal="contact-edit-modal"', false)
            ->assertSee('action="'.route('contacts.update', $contact).'"', false)->assertSee('Module 1');

        $this->put(route('contacts.update', $contact), [
            'company_id' => $contact->company_id, 'full_name' => 'Edited Person', 'email' => $contact->email,
            'product_role' => 'finance', 'preferred_channel' => 'meeting', 'modules' => [$m->module_id], '_modal' => 'contact-edit',
        ])->assertRedirect(route('contacts.show', $contact));

        $this->get(route('contacts.show', $contact))->assertSee('Edited Person')->assertSee('Finance');
        $this->assertSame(1, $contact->modules()->count());
    }

    public function test_contact_page_has_no_edit_popup_for_agents(): void
    {
        $this->actingAs($this->agent)->get(route('contacts.show', Contact::factory()->create()))
            ->assertOk()->assertDontSee('contact-edit-modal')->assertDontSee('Edit contact');
    }

    public function test_ticket_page_edits_in_a_popup_and_stays_on_the_page(): void
    {
        $ticket = Ticket::factory()->assignedTo($this->agent)->create(['title' => 'Before edit']);

        $this->actingAs($this->agent)->get(route('tickets.show', $ticket))
            ->assertSee('<dialog id="ticket-edit-modal"', false)->assertSee('data-modal="ticket-edit-modal"', false)
            ->assertSee('action="'.route('tickets.update', $ticket).'"', false)->assertSee('value="Before edit"', false);

        $this->put(route('tickets.update', $ticket), [
            'topic' => 'bug', 'priority' => 'critical', 'status' => 'in_progress', 'title' => 'After edit', '_modal' => 'ticket-edit',
        ])->assertRedirect(route('tickets.show', $ticket));

        $this->get(route('tickets.show', $ticket))->assertSee('After edit');
    }

    public function test_a_failed_ticket_edit_reopens_its_popup(): void
    {
        $ticket = Ticket::factory()->assignedTo($this->agent)->create(['title' => 'Keep me']);

        $this->actingAs($this->agent)->from(route('tickets.show', $ticket))
            ->put(route('tickets.update', $ticket), ['topic' => 'bug', 'priority' => 'low', 'status' => 'open', 'title' => '', '_modal' => 'ticket-edit'])
            ->assertRedirect(route('tickets.show', $ticket));

        $this->assertSame(1, $this->reopened($this->get(route('tickets.show', $ticket))->getContent()));
        $this->assertSame('Keep me', $ticket->fresh()->title);
    }

    // ---- general ---------------------------------------------------------

    public function test_the_stand_alone_create_pages_still_work_without_javascript(): void
    {
        $this->actingAs($this->admin);

        foreach (['companies.create', 'contacts.create', 'tickets.create', 'admin.users.create', 'templates.create'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_every_popup_dialog_is_self_contained(): void
    {
        $this->actingAs($this->admin);

        foreach (['companies.index', 'contacts.index', 'tickets.index', 'admin.users.index', 'templates.index'] as $route) {
            $html = $this->get(route($route))->getContent();

            $this->assertStringContainsString('<dialog', $html, $route);
            $this->assertStringContainsString('data-close', $html, "$route has a Cancel/close button");
            $this->assertStringContainsString('name="_token"', $html, "$route dialog carries a CSRF token");
            $this->assertSame(substr_count($html, '<dialog'), substr_count($html, '</dialog>'), "$route dialogs are closed");
        }
    }
}
