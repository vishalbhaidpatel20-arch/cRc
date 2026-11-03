<?php

namespace Tests\Feature\E2E;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Interaction;
use App\Models\MessageTemplate;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Milestone 4 journey: admin sets up a template, an agent uses it to log a conversation on a
 * ticket and on a contact, the contact's last-contacted date moves, and the log stays append-only.
 */
class ConversationJourneyTest extends TestCase
{
    use JourneyHelpers;
    use RefreshDatabase;

    public function test_admin_creates_a_template_and_an_agent_logs_conversations_with_it(): void
    {
        $admin = $this->makeAdmin(['full_name' => 'Boss Admin']);
        $anna = $this->makeAgent(['full_name' => 'Anna Agent', 'email' => 'anna@example.com']);
        $ben = $this->makeAgent(['full_name' => 'Ben Agent', 'email' => 'ben@example.com']);
        $company = Company::factory()->create(['name' => 'Initech']);
        $peter = Contact::factory()->for($company)->create(['full_name' => 'Peter Gibbons']);
        $ticket = Ticket::factory()->assignedTo($anna)->create(['contact_id' => $peter->contact_id, 'company_id' => $company->company_id, 'title' => 'TPS report totals']);
        $bensTicket = Ticket::factory()->assignedTo($ben)->create(['contact_id' => $peter->contact_id, 'company_id' => $company->company_id, 'title' => 'Ben secret printer fix']);

        // 1. Admin writes a template; a typo'd placeholder is refused, then fixed.
        $this->loginAs($admin->email)->assertSee('Admin dashboard');
        $this->browse(route('templates.create'))->assertOk()->assertSee('Message *');
        $this->submit('POST', route('templates.store'), [
            'name' => 'Progress update', 'category' => 'follow_up', 'channel' => 'email',
            'subject' => 'Update on {{ticket_no}}', 'body' => 'Hi {{nmae}}, quick update.',
        ], route('templates.create'))->assertSee('Unknown placeholder');
        $this->assertDatabaseMissing('message_templates', ['name' => 'Progress update']);

        $this->submit('POST', route('templates.store'), [
            'name' => 'Progress update', 'category' => 'follow_up', 'channel' => 'email',
            'subject' => 'Update on {{ticket_no}}', 'body' => 'Hi {{name}} from {{company}}, {{agent_name}} is working on {{ticket_title}}.',
        ], route('templates.create'))->assertOk()->assertSee('Template Progress update created.')->assertSee('Progress update');
        $template = MessageTemplate::where('name', 'Progress update')->firstOrFail();
        $this->logout();

        // 2. Anna opens her ticket, inserts the template, and logs the conversation.
        $this->loginAs($anna->email);
        $this->browse(route('tickets.show', $ticket))->assertOk()->assertSee('Log a conversation')->assertSee('Progress update (email)');
        $page = $this->browse(route('tickets.show', ['ticket' => $ticket, 'template' => $template->template_id]))
            ->assertOk()
            ->assertSee('Hi Peter Gibbons from Initech, Anna Agent is working on TPS report totals.')
            ->assertSee('Update on '.$ticket->ticket_no);
        $this->assertMatchesRegularExpression('/<option value="email"\s+selected>/', $page->getContent());

        $this->submit('POST', route('tickets.interactions.store', $ticket), [
            'contact_id' => $peter->contact_id, 'topic' => 'follow_up', 'channel' => 'email', 'direction' => 'outbound',
            'summary' => 'Hi Peter Gibbons from Initech, Anna Agent is working on TPS report totals.',
        ], route('tickets.show', $ticket))
            ->assertOk()->assertSee('Conversation logged.')->assertSee('Anna Agent')->assertSee('Email, outbound');

        $this->assertNotNull($peter->fresh()->last_contacted_at, 'the DB trigger sets last_contacted_at');

        // 3. Anna logs a phone call from the contact page, linked to her own ticket only.
        $this->browse(route('contacts.show', $peter))->assertOk()
            ->assertSee('TPS report totals')->assertDontSee('Ben secret printer fix');
        $this->submit('POST', route('contacts.interactions.store', $peter), [
            'topic' => 'query', 'channel' => 'call', 'direction' => 'inbound',
            'summary' => 'Peter called to ask about the export.', 'ticket_id' => $bensTicket->ticket_id,
        ], route('contacts.show', $peter))->assertSee('Choose one of your tickets');
        $this->submit('POST', route('contacts.interactions.store', $peter), [
            'topic' => 'query', 'channel' => 'call', 'direction' => 'inbound',
            'summary' => 'Peter called to ask about the export.', 'ticket_id' => $ticket->ticket_id,
        ], route('contacts.show', $peter))->assertOk()->assertSee('Conversation logged.')->assertSee('Peter called to ask about the export.');

        // 4. The daily log shows everything, with the right author and ticket link.
        $this->browse(route('interactions.index'))->assertSee('Peter called to ask about the export.')->assertSee($ticket->ticket_no);
        $this->browse(route('interactions.index', ['mine' => 1, 'channel' => 'call']))->assertSee('Peter called')->assertDontSee('is working on TPS');
        $this->assertSame(2, Interaction::where('user_id', $anna->user_id)->count());
        $this->logout();

        // 5. Ben can read the log, but never sees or links to Anna's ticket; the log is append-only.
        $this->loginAs($ben->email);
        $this->browse(route('interactions.index'))->assertSee('Peter called to ask about the export.')
            ->assertSee("another agent's ticket", false)->assertDontSee($ticket->ticket_no);
        $this->freshRequest()->get(route('tickets.show', $ticket))->assertNotFound();
        $first = Interaction::firstOrFail();
        $this->freshRequest()->put("/interactions/{$first->interaction_id}", ['summary' => 'rewritten'])->assertNotFound();
        $this->freshRequest()->delete("/interactions/{$first->interaction_id}")->assertNotFound();
        $this->assertNotSame('rewritten', $first->fresh()->summary);
    }
}
