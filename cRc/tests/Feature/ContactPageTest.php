<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactProfile;
use App\Models\FollowUp;
use App\Models\Interaction;
use App\Models\ProductModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The contact page (details, modules, log form, history) plus follow-ups and product modules. */
class ContactPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->alice = User::factory()->agent()->create(['full_name' => 'Alice Agent']);
        $this->bob = User::factory()->agent()->create(['full_name' => 'Bob Agent']);
    }

    private function logPayload(array $over = []): array
    {
        return array_merge(['topic' => 'query', 'channel' => 'call', 'direction' => 'inbound', 'summary' => 'Talked about invoices.'], $over);
    }

    // ---- the page --------------------------------------------------------

    public function test_page_shows_avatar_details_main_contact_and_modules(): void
    {
        $contact = Contact::factory()->create([
            'full_name' => 'Rohan Shah', 'position' => 'Operations manager', 'is_primary' => true,
            'business_name' => 'Apex Retail Pune', 'department' => 'Operations', 'product_role' => 'decision_maker',
            'preferred_channel' => 'whatsapp', 'phone' => '9800000210', 'whatsapp_no' => '9800000210',
        ]);
        ContactProfile::create(['contact_id' => $contact->contact_id, 'preferred_time' => '10am to 1pm']);
        $billing = ProductModule::create(['name' => 'Billing']);
        $reports = ProductModule::create(['name' => 'Reports']);
        ProductModule::create(['name' => 'Unused']);
        $contact->modules()->attach([$billing->module_id, $reports->module_id]);

        $this->actingAs($this->alice)->get(route('contacts.show', $contact))->assertOk()
            ->assertSee('>RS<', false)
            ->assertSee('Rohan Shah')->assertSee('Operations manager')
            ->assertSee('Main contact')
            ->assertSee('Apex Retail Pune')->assertSee('Decision maker')
            ->assertSee('Same as phone')
            ->assertSee('WhatsApp, 10am to 1pm')
            ->assertSee('Uses these modules')->assertSee('Billing')->assertSee('Reports')->assertDontSee('Unused');
    }

    public function test_initials(): void
    {
        $this->assertSame('RS', (new Contact(['full_name' => 'Rohan Shah']))->initials());
        $this->assertSame('M', (new Contact(['full_name' => 'madonna']))->initials());
        $this->assertSame('AB', (new Contact(['full_name' => '  anna   bell   cole ']))->initials());
        $this->assertSame('?', (new Contact(['full_name' => '']))->initials());
    }

    public function test_page_without_profile_or_modules_still_renders(): void
    {
        $contact = Contact::factory()->create(['preferred_channel' => 'email', 'whatsapp_no' => null]);

        $this->actingAs($this->alice)->get(route('contacts.show', $contact))->assertOk()
            ->assertSee('None recorded')->assertSee('Email')->assertDontSee('Main contact');
    }

    public function test_log_form_comes_before_the_history_and_both_are_present(): void
    {
        $contact = Contact::factory()->create();
        Interaction::factory()->create(['contact_id' => $contact->contact_id, 'summary' => 'An earlier chat']);

        $html = $this->actingAs($this->alice)->get(route('contacts.show', $contact))->getContent();

        $this->assertLessThan(strpos($html, 'Conversation history'), strpos($html, 'Log a conversation'));
        $this->assertStringContainsString('1 conversation<', str_replace(['</span>'], '<', $html));
        $this->assertStringContainsString('An earlier chat', $html);
        $this->assertStringContainsString('placeholder="What did you discuss?"', $html);
        $this->assertStringContainsString('Insert message template...', $html);
        $this->assertStringContainsString('name="follow_up_on"', $html);
        $this->assertStringContainsString('Save log', $html);
    }

    public function test_history_shows_the_count_and_links_to_the_full_log_when_long(): void
    {
        $contact = Contact::factory()->create(['full_name' => 'Busy Bee']);
        Interaction::factory()->count(17)->create(['contact_id' => $contact->contact_id]);

        $this->actingAs($this->alice)->get(route('contacts.show', $contact))
            ->assertSee('17 conversations')->assertSee('See all 17 conversations with Busy Bee');
    }

    public function test_history_row_labels(): void
    {
        $contact = Contact::factory()->create();
        Interaction::factory()->create(['contact_id' => $contact->contact_id, 'channel' => 'whatsapp', 'direction' => 'inbound', 'topic' => 'bug', 'summary' => 'WA row', 'interaction_at' => now()->subDay()->setTime(10, 45)]);
        Interaction::factory()->create(['contact_id' => $contact->contact_id, 'channel' => 'meeting', 'direction' => 'outbound', 'topic' => 'customization', 'summary' => 'Meeting row', 'interaction_at' => now()->subDays(20)]);

        $this->actingAs($this->alice)->get(route('contacts.show', $contact))
            ->assertSee('WhatsApp, inbound')->assertSee('Meeting, outbound')
            ->assertSee('Yesterday, 10:45')->assertSee('Bug')->assertSee('Customization');
    }

    public function test_when_label_formats(): void
    {
        $at = fn ($t) => (new Interaction)->forceFill(['interaction_at' => $t])->whenLabel();

        $this->assertSame('Today, 09:05', $at(now()->setTime(9, 5)));
        $this->assertSame('Yesterday, 23:59', $at(now()->subDay()->setTime(23, 59)));
        $this->assertSame(now()->subYears(2)->format('j M Y'), $at(now()->subYears(2)));
    }

    public function test_channel_labels(): void
    {
        $this->assertSame('WhatsApp', Interaction::channelLabel('whatsapp'));
        $this->assertSame('Call', Interaction::channelLabel('call'));
        $this->assertSame('Email', Interaction::channelLabel('email'));
        $this->assertSame('Meeting', Interaction::channelLabel('meeting'));
    }

    public function test_every_template_is_embedded_filled_in_for_the_picker(): void
    {
        $contact = Contact::factory()->create(['full_name' => 'Pat Picker']);
        \App\Models\MessageTemplate::factory()->create(['name' => 'Hello tpl', 'body' => 'Dear {{name}}, welcome.', 'subject' => 'Hi {{name}}']);

        $this->actingAs($this->alice)->get(route('contacts.show', $contact))
            ->assertSee('data-body="Dear Pat Picker, welcome."', false)
            ->assertSee('data-subject="Hi Pat Picker"', false)
            ->assertSee('id="tpl-picker"', false);
    }

    public function test_the_page_is_read_only_for_agents_but_admins_get_edit_buttons(): void
    {
        $contact = Contact::factory()->create();

        $this->actingAs($this->alice)->get(route('contacts.show', $contact))
            ->assertDontSee('Edit contact')->assertDontSee('Deactivate')->assertSee('+ New ticket');

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->admin)->get(route('contacts.show', $contact))
            ->assertSee('Edit contact')->assertSee('Deactivate');
    }

    public function test_inactive_contact_hides_the_log_form_but_keeps_the_history(): void
    {
        $contact = Contact::factory()->inactive()->create();
        Interaction::factory()->create(['contact_id' => $contact->contact_id, 'summary' => 'Old chat kept']);

        $this->actingAs($this->alice)->get(route('contacts.show', $contact))
            ->assertDontSee('Log a conversation')->assertSee('Old chat kept')->assertSee('Inactive');
    }

    // ---- follow-ups ------------------------------------------------------

    public function test_a_follow_up_date_creates_a_pending_reminder_for_the_author(): void
    {
        $contact = Contact::factory()->create();

        $this->actingAs($this->alice)->post(route('contacts.interactions.store', $contact), $this->logPayload([
            'follow_up_on' => now()->addDay()->toDateString(),
        ]))->assertSessionHasNoErrors();

        $i = Interaction::firstOrFail();
        $f = FollowUp::firstOrFail();
        $this->assertSame($i->interaction_id, $f->interaction_id);
        $this->assertSame($contact->contact_id, $f->contact_id);
        $this->assertSame($this->alice->user_id, $f->assigned_to);
        $this->assertSame('pending', $f->status);
        $this->assertSame(now()->addDay()->toDateString(), $f->due_date->toDateString());
        $this->assertSame('Follow up: Talked about invoices.', $f->description);

        $this->get(route('contacts.show', $contact))->assertSee('Follow up tomorrow');
    }

    public function test_no_date_means_no_follow_up(): void
    {
        $this->actingAs($this->alice)->post(route('contacts.interactions.store', Contact::factory()->create()), $this->logPayload());

        $this->assertDatabaseCount('interactions', 1);
        $this->assertDatabaseCount('follow_ups', 0);
    }

    public function test_follow_up_date_cannot_be_in_the_past_or_invalid(): void
    {
        $contact = Contact::factory()->create();
        $this->actingAs($this->alice);

        $this->post(route('contacts.interactions.store', $contact), $this->logPayload(['follow_up_on' => now()->subDay()->toDateString()]))
            ->assertSessionHasErrors('follow_up_on');
        $this->post(route('contacts.interactions.store', $contact), $this->logPayload(['follow_up_on' => 'soon']))
            ->assertSessionHasErrors('follow_up_on');

        $this->assertDatabaseCount('interactions', 0);
        $this->assertDatabaseCount('follow_ups', 0);
    }

    public function test_follow_up_today_is_allowed_and_a_long_summary_is_shortened_in_the_description(): void
    {
        $this->actingAs($this->alice)->post(route('contacts.interactions.store', Contact::factory()->create()), $this->logPayload([
            'summary' => str_repeat('word ', 100), 'follow_up_on' => now()->toDateString(),
        ]))->assertSessionHasNoErrors();

        $this->assertLessThanOrEqual(255, strlen(FollowUp::firstOrFail()->description));
    }

    public function test_follow_up_from_a_ticket_conversation(): void
    {
        $ticket = \App\Models\Ticket::factory()->assignedTo($this->alice)->create();

        $this->actingAs($this->alice)->post(route('tickets.interactions.store', $ticket), $this->logPayload([
            'contact_id' => $ticket->contact_id, 'follow_up_on' => now()->addDays(3)->toDateString(),
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('follow_ups', ['contact_id' => $ticket->contact_id, 'assigned_to' => $this->alice->user_id, 'status' => 'pending']);
    }

    public function test_follow_up_labels(): void
    {
        $mk = fn ($d, $s = 'pending') => (new FollowUp)->forceFill(['due_date' => $d, 'status' => $s]);

        $this->assertSame('today', $mk(today())->dueLabel());
        $this->assertSame('tomorrow', $mk(today()->addDay())->dueLabel());
        $this->assertSame('yesterday', $mk(today()->subDay())->dueLabel());
        $this->assertFalse($mk(today())->isOverdue());
        $this->assertTrue($mk(today()->subDay())->isOverdue());
        $this->assertFalse($mk(today()->subDay(), 'done')->isOverdue());
    }

    public function test_overdue_done_and_pending_tags_in_the_history(): void
    {
        $contact = Contact::factory()->create();
        $late = Interaction::factory()->create(['contact_id' => $contact->contact_id, 'summary' => 'late one']);
        $done = Interaction::factory()->create(['contact_id' => $contact->contact_id, 'summary' => 'done one']);
        FollowUp::create(['contact_id' => $contact->contact_id, 'interaction_id' => $late->interaction_id, 'assigned_to' => $this->alice->user_id, 'due_date' => today()->subDays(2), 'description' => 'x', 'status' => 'pending']);
        FollowUp::create(['contact_id' => $contact->contact_id, 'interaction_id' => $done->interaction_id, 'assigned_to' => $this->alice->user_id, 'due_date' => today()->subDays(2), 'description' => 'y', 'status' => 'done']);

        $this->actingAs($this->alice)->get(route('contacts.show', $contact))
            ->assertSee('Follow-up overdue')->assertSee('Follow-up done');
    }

    public function test_dashboard_lists_my_due_followups_and_marks_them_done(): void
    {
        $contact = Contact::factory()->create(['full_name' => 'Due Dora']);
        $mine = FollowUp::create(['contact_id' => $contact->contact_id, 'assigned_to' => $this->alice->user_id, 'due_date' => today(), 'description' => 'Call Dora back', 'status' => 'pending']);
        FollowUp::create(['contact_id' => $contact->contact_id, 'assigned_to' => $this->alice->user_id, 'due_date' => today()->addDays(5), 'description' => 'Future one', 'status' => 'pending']);
        FollowUp::create(['contact_id' => $contact->contact_id, 'assigned_to' => $this->bob->user_id, 'due_date' => today(), 'description' => 'Bobs reminder', 'status' => 'pending']);
        FollowUp::create(['contact_id' => $contact->contact_id, 'assigned_to' => $this->alice->user_id, 'due_date' => today(), 'description' => 'Already done thing', 'status' => 'done']);

        $this->actingAs($this->alice)->get(route('agent.dashboard'))
            ->assertSee('Call Dora back')->assertSee('Due today')
            ->assertDontSee('Future one')->assertDontSee('Bobs reminder')->assertDontSee('Already done thing');

        $this->patch(route('follow-ups.done', $mine))->assertRedirect();
        $this->assertSame('done', $mine->fresh()->status);
        $this->assertNotNull($mine->fresh()->completed_at);
        $this->get(route('agent.dashboard'))->assertDontSee('Call Dora back');
    }

    public function test_overdue_follow_up_is_flagged_on_the_dashboard(): void
    {
        FollowUp::create(['contact_id' => Contact::factory()->create()->contact_id, 'assigned_to' => $this->alice->user_id, 'due_date' => today()->subDays(3), 'description' => 'Missed call', 'status' => 'pending']);

        $this->actingAs($this->alice)->get(route('agent.dashboard'))->assertSee('Missed call')->assertSee('Overdue');
    }

    public function test_only_the_owner_or_an_admin_can_mark_a_follow_up_done(): void
    {
        $f = FollowUp::create(['contact_id' => Contact::factory()->create()->contact_id, 'assigned_to' => $this->alice->user_id, 'due_date' => today(), 'description' => 'x', 'status' => 'pending']);

        $this->actingAs($this->bob)->patch(route('follow-ups.done', $f))->assertForbidden();
        $this->assertSame('pending', $f->fresh()->status);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->admin)->patch(route('follow-ups.done', $f))->assertRedirect();
        $this->assertSame('done', $f->fresh()->status);
    }

    // ---- modules ---------------------------------------------------------

    public function test_admin_manages_modules_and_agents_cannot(): void
    {
        $m = ProductModule::create(['name' => 'Billing']);

        $this->actingAs($this->alice);
        $this->get(route('admin.modules.index'))->assertForbidden();
        $this->post(route('admin.modules.store'), ['name' => 'Hack'])->assertForbidden();
        $this->put(route('admin.modules.update', $m), ['name' => 'Hacked'])->assertForbidden();
        $this->assertDatabaseMissing('product_modules', ['name' => 'Hack']);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->admin);
        $this->get(route('admin.modules.index'))->assertOk()->assertSee('Billing');
        $this->post(route('admin.modules.store'), ['name' => 'Reports', 'description' => 'All reports'])->assertRedirect(route('admin.modules.index'));
        $this->put(route('admin.modules.update', $m), ['name' => 'Invoicing'])->assertSessionHasNoErrors();
        $this->assertSame('Invoicing', $m->fresh()->name);
        $this->delete("/admin/modules/{$m->module_id}")->assertStatus(405);
    }

    public function test_module_names_are_validated_and_unique(): void
    {
        ProductModule::create(['name' => 'Billing']);
        $other = ProductModule::create(['name' => 'Reports']);
        $this->actingAs($this->admin);

        $this->post(route('admin.modules.store'), ['name' => 'Billing'])->assertSessionHasErrors('name');
        $this->post(route('admin.modules.store'), ['name' => ''])->assertSessionHasErrors('name');
        $this->post(route('admin.modules.store'), ['name' => str_repeat('x', 101)])->assertSessionHasErrors('name');
        $this->put(route('admin.modules.update', $other), ['name' => 'Billing'])->assertSessionHasErrors('name');
        $this->put(route('admin.modules.update', $other), ['name' => 'Reports'])->assertSessionHasNoErrors();
    }

    public function test_admin_sets_a_contacts_modules_and_best_time_when_editing(): void
    {
        $contact = Contact::factory()->create();
        $a = ProductModule::create(['name' => 'Module 1']);
        $b = ProductModule::create(['name' => 'Module 2']);
        $payload = [
            'company_id' => $contact->company_id, 'full_name' => $contact->full_name, 'email' => $contact->email,
            'product_role' => 'end_user', 'preferred_channel' => 'whatsapp',
        ];
        $this->actingAs($this->admin);

        $this->get(route('contacts.edit', $contact))->assertOk()->assertSee('Module 1')->assertSee('Best time to reach');

        $this->put(route('contacts.update', $contact), $payload + ['modules' => [$a->module_id, $b->module_id], 'preferred_time' => 'Mornings'])
            ->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([$a->module_id, $b->module_id], $contact->modules()->pluck('product_modules.module_id')->all());
        $this->assertSame('Mornings', $contact->profile()->first()->preferred_time);

        $this->put(route('contacts.update', $contact), $payload + ['modules' => [$a->module_id], 'preferred_time' => ''])
            ->assertSessionHasNoErrors();
        $this->assertSame([$a->module_id], $contact->modules()->pluck('product_modules.module_id')->all());
        $this->assertNull($contact->profile()->first()->preferred_time);

        $this->put(route('contacts.update', $contact), $payload)->assertSessionHasNoErrors();
        $this->assertSame(0, $contact->modules()->count(), 'unticking every module clears them');
    }

    public function test_creating_a_contact_can_set_modules_and_rejects_unknown_ones(): void
    {
        $company = \App\Models\Company::factory()->create();
        $a = ProductModule::create(['name' => 'Module 1']);
        $payload = ['company_id' => $company->company_id, 'full_name' => 'New Person', 'email' => 'new.person@example.com', 'product_role' => 'end_user', 'preferred_channel' => 'call'];
        $this->actingAs($this->admin);

        $this->post(route('contacts.store'), $payload + ['modules' => [99999]])->assertSessionHasErrors('modules.0');
        $this->assertDatabaseMissing('contacts', ['email' => 'new.person@example.com']);

        $this->post(route('contacts.store'), $payload + ['modules' => [$a->module_id]])->assertSessionHasNoErrors();
        $this->assertSame(1, Contact::where('email', 'new.person@example.com')->firstOrFail()->modules()->count());
    }

    public function test_agents_cannot_change_modules_through_the_contact_routes(): void
    {
        $contact = Contact::factory()->create();
        $a = ProductModule::create(['name' => 'Module 1']);

        $this->actingAs($this->alice)->put(route('contacts.update', $contact), [
            'company_id' => $contact->company_id, 'full_name' => 'X', 'email' => 'x@example.com',
            'product_role' => 'end_user', 'preferred_channel' => 'call', 'modules' => [$a->module_id],
        ])->assertForbidden();

        $this->assertSame(0, $contact->modules()->count());
    }
}
