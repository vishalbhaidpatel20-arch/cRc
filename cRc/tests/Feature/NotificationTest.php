<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketAssigned;
use App\Notifications\TicketCompleted;
use App\Support\NotificationPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The notification system: an agent is told when a ticket is assigned to them, the admin who assigned a ticket is told
 * when the agent completes it, and the header bell / feed / full list show each user only their own.
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $otherAdmin;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['full_name' => 'Ada Admin']);
        $this->otherAdmin = User::factory()->admin()->create(['full_name' => 'Olga Other']);
        $this->alice = User::factory()->agent()->create(['full_name' => 'Alice Agent']);
        $this->bob = User::factory()->agent()->create(['full_name' => 'Bob Agent']);
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user);
    }

    private function save(User $who, Ticket $ticket, array $over = [])
    {
        return $this->as($who)->put(route('tickets.update', $ticket), array_merge([
            'topic' => $ticket->topic, 'priority' => $ticket->priority, 'status' => $ticket->status,
            'title' => $ticket->title, 'description' => 'Details', 'due_at' => null,
        ], $over));
    }

    private function assign(User $admin, Ticket $ticket, User $agent)
    {
        return $this->as($admin)->post(route('tickets.assign', $ticket), ['assigned_to' => $agent->user_id, 'reason' => 'Please take it']);
    }

    // ---- assigned: the agent is told ---------------------------------------

    public function test_assigning_a_ticket_notifies_the_agent_and_shows_in_their_feed(): void
    {
        $ticket = Ticket::factory()->unassigned()->create(['ticket_no' => 'TKT-0042', 'title' => 'Fix the printer']);

        $this->assign($this->admin, $ticket, $this->alice);

        $n = $this->alice->notifications()->first();
        $this->assertSame(TicketAssigned::class, $n->type);
        $this->assertSame('Ada Admin assigned you TKT-0042: Fix the printer', NotificationPresenter::present($n)['text']);
        $this->assertCount(0, $this->bob->notifications, 'the other agent hears nothing');
        $this->assertCount(0, $this->admin->notifications, 'the admin is not told about their own action');

        $this->as($this->alice)->getJson(route('notifications.feed'))->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('newest_unread_text', 'Ada Admin assigned you TKT-0042: Fix the printer')
            ->assertJsonPath('newest_unread_id', $n->id)
            ->assertSee('Fix the printer');
    }

    // ---- completed: the admin who assigned it is told ----------------------

    public function test_an_agent_completing_a_ticket_notifies_the_admin_who_assigned_it(): void
    {
        $ticket = Ticket::factory()->unassigned()->create(['ticket_no' => 'TKT-0007', 'title' => 'Report is slow']);
        $this->assign($this->admin, $ticket, $this->alice);

        $this->save($this->alice, $ticket->fresh(), ['status' => 'resolved'])->assertRedirect(route('tickets.show', $ticket));

        $n = $this->admin->notifications()->where('type', TicketCompleted::class)->first();
        $this->assertNotNull($n, 'the assigning admin is notified');
        $this->assertSame('Alice Agent', $n->data['completed_by']);
        $this->assertSame($ticket->ticket_id, $n->data['ticket_id']);
        $this->assertSame('Alice Agent completed TKT-0007: Report is slow', NotificationPresenter::present($n)['text']);

        $this->assertCount(0, $this->otherAdmin->notifications, 'only the admin who assigned it is told');
        $this->assertCount(0, $this->alice->notifications()->where('type', TicketCompleted::class)->get());
    }

    public function test_the_latest_assigner_is_the_one_told_after_a_reassignment(): void
    {
        $ticket = Ticket::factory()->unassigned()->create();
        $this->assign($this->admin, $ticket, $this->bob);
        $this->assign($this->otherAdmin, $ticket, $this->alice);

        $this->save($this->alice, $ticket->fresh(), ['status' => 'resolved']);

        $this->assertCount(1, $this->otherAdmin->notifications()->where('type', TicketCompleted::class)->get());
        $this->assertCount(0, $this->admin->notifications()->where('type', TicketCompleted::class)->get());
    }

    public function test_without_an_assignment_record_every_active_admin_is_told(): void
    {
        $inactive = User::factory()->admin()->create(['is_active' => 0]);
        $ticket = Ticket::factory()->assignedTo($this->alice)->create();   // no ticket_assignments row

        $this->save($this->alice, $ticket, ['status' => 'resolved']);

        $this->assertCount(1, $this->admin->notifications);
        $this->assertCount(1, $this->otherAdmin->notifications);
        $this->assertCount(0, $inactive->notifications, 'deactivated admins are skipped');
    }

    public function test_only_the_move_to_resolved_by_the_holder_counts(): void
    {
        $ticket = Ticket::factory()->unassigned()->create();
        $this->assign($this->admin, $ticket, $this->alice);
        $this->admin->notifications()->delete();

        // Other status changes and edits: no completion alert.
        $this->save($this->alice, $ticket->fresh(), ['status' => 'in_progress', 'title' => 'New title']);
        $this->assertCount(0, $this->admin->fresh()->notifications);

        // Resolving: one alert. Saving it again (still resolved): no second alert.
        $this->save($this->alice, $ticket->fresh(), ['status' => 'resolved']);
        $this->save($this->alice, $ticket->fresh(), ['status' => 'resolved', 'title' => 'Edited again']);
        $this->assertCount(1, $this->admin->fresh()->notifications);

        // An admin resolving a ticket is not an agent completing a task: nobody is told.
        $other = Ticket::factory()->assignedTo($this->bob)->create();
        $this->save($this->admin, $other, ['status' => 'resolved']);
        $this->assertCount(1, $this->admin->fresh()->notifications);
        $this->assertCount(0, $this->otherAdmin->fresh()->notifications);
    }

    // ---- feed, open, mark read, list ----------------------------------------

    public function test_each_user_only_sees_their_own_notifications(): void
    {
        $a = Ticket::factory()->unassigned()->create(['title' => 'Alices secret ticket']);
        $b = Ticket::factory()->unassigned()->create(['title' => 'Bobs secret ticket']);
        $this->assign($this->admin, $a, $this->alice);
        $this->assign($this->admin, $b, $this->bob);

        $feed = $this->as($this->alice)->getJson(route('notifications.feed'))->assertOk()->assertJsonPath('unread', 1);
        $this->assertStringContainsString('Alices secret ticket', $feed->json('html'));
        $this->assertStringNotContainsString('Bobs secret ticket', $feed->json('html'));

        $this->as($this->alice)->get(route('notifications.index'))->assertOk()
            ->assertSee('Alices secret ticket')->assertDontSee('Bobs secret ticket');
    }

    public function test_opening_a_notification_marks_it_read_and_goes_to_its_ticket(): void
    {
        $ticket = Ticket::factory()->unassigned()->create();
        $this->assign($this->admin, $ticket, $this->alice);
        $n = $this->alice->notifications()->first();

        $this->as($this->alice)->get(route('notifications.open', $n->id))->assertRedirect(route('tickets.show', $ticket));

        $this->assertNotNull($n->fresh()->read_at);
        $this->assertSame(0, $this->alice->unreadNotifications()->count());
    }

    public function test_nobody_can_open_someone_elses_notification(): void
    {
        $ticket = Ticket::factory()->unassigned()->create();
        $this->assign($this->admin, $ticket, $this->alice);
        $n = $this->alice->notifications()->first();

        $this->as($this->bob)->get(route('notifications.open', $n->id))->assertNotFound();
        $this->assertNull($n->fresh()->read_at, 'and it stays unread');
        $this->as($this->bob)->get(route('notifications.open', 'not-a-real-id'))->assertNotFound();
    }

    public function test_mark_all_read_works_for_ajax_and_plain_posts_and_only_for_the_caller(): void
    {
        $this->assign($this->admin, Ticket::factory()->unassigned()->create(), $this->alice);
        $this->assign($this->admin, Ticket::factory()->unassigned()->create(), $this->bob);

        $this->as($this->alice)->postJson(route('notifications.read'))->assertOk()->assertJson(['unread' => 0]);
        $this->assertSame(0, $this->alice->unreadNotifications()->count());
        $this->assertSame(1, $this->bob->unreadNotifications()->count());

        $this->as($this->bob)->post(route('notifications.read'))->assertRedirect();
        $this->assertSame(0, $this->bob->unreadNotifications()->count());
    }

    public function test_the_bell_is_in_the_header_with_the_unread_count_and_a_noscript_friendly_link(): void
    {
        $this->assign($this->admin, Ticket::factory()->unassigned()->create(), $this->alice);
        $this->assign($this->admin, Ticket::factory()->unassigned()->create(), $this->alice);

        $html = $this->as($this->alice)->get(route('agent.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('data-bell', $html);
        $this->assertMatchesRegularExpression('/<span class="bell-count" data-bell-count >2<\/span>/', $html);
        $this->assertStringContainsString('href="'.route('notifications.index').'"', $html);
        $this->assertStringContainsString('js/notifications.js', $html);
        $this->assertStringContainsString('name="csrf-token"', $html);

        // Nothing unread: the badge is hidden.
        $bob = $this->as($this->bob)->get(route('agent.dashboard'))->getContent();
        $this->assertMatchesRegularExpression('/data-bell-count\s+hidden/', $bob);
    }

    public function test_an_agent_who_lost_the_ticket_no_longer_sees_its_title_or_link(): void
    {
        $ticket = Ticket::factory()->unassigned()->create(['ticket_no' => 'TKT-0555', 'title' => 'Confidential merger report']);
        $this->assign($this->admin, $ticket, $this->alice);
        $n = $this->alice->notifications()->first();

        // While it is hers: title shown, link goes to the ticket.
        $this->as($this->alice)->getJson(route('notifications.feed'))->assertSee('Confidential merger report');

        // Reassigned to Bob: Alice keeps the notification but loses the title, and opening it does not 404.
        $this->assign($this->admin, $ticket->fresh(), $this->bob);

        $feed = $this->as($this->alice)->getJson(route('notifications.feed'))->assertOk();
        $this->assertStringNotContainsString('Confidential merger report', $feed->json('html'));
        $this->assertStringContainsString('TKT-0555 (no longer yours)', $feed->json('html'));
        $this->assertSame('Ada Admin assigned you TKT-0555 (no longer yours)', $feed->json('newest_unread_text'));

        $this->as($this->alice)->get(route('notifications.index'))->assertOk()
            ->assertDontSee('Confidential merger report')->assertSee('TKT-0555 (no longer yours)');

        $this->as($this->alice)->get(route('notifications.open', $n->id))
            ->assertRedirect(route('tickets.index'))->assertSessionHas('status');
        $this->assertNotNull($n->fresh()->read_at);

        // Bob, who has it now, sees the title.
        $this->as($this->bob)->getJson(route('notifications.feed'))->assertSee('Confidential merger report');
    }

    public function test_the_full_list_shows_both_kinds_and_an_empty_state(): void
    {
        $this->as($this->admin)->get(route('notifications.index'))->assertOk()->assertSee('No notifications yet.');

        $ticket = Ticket::factory()->unassigned()->create(['ticket_no' => 'TKT-0100', 'title' => 'Both kinds']);
        $this->assign($this->admin, $ticket, $this->alice);
        $this->save($this->alice, $ticket->fresh(), ['status' => 'resolved']);

        // Look at the table row itself: the same text also appears in the bell dropdown of every page.
        $admin = $this->as($this->admin)->get(route('notifications.index'))->assertOk()->assertSee('Completed')->assertSee('New')->getContent();
        $this->assertMatchesRegularExpression('/<tr id="notification-[^"]+">.*?Alice Agent completed TKT-0100: Both kinds.*?<\/tr>/s', $admin);
        $this->assertStringNotContainsString('(no longer yours)', $admin, 'an admin can see every ticket');

        $alice = $this->as($this->alice)->get(route('notifications.index'))->assertOk()->assertSee('Assigned')->getContent();
        $this->assertMatchesRegularExpression('/<tr id="notification-[^"]+">.*?Ada Admin assigned you TKT-0100: Both kinds.*?<\/tr>/s', $alice);
    }

    public function test_the_presenter_handles_old_and_unknown_notifications(): void
    {
        $ticket = Ticket::factory()->unassigned()->create();
        $this->assign($this->admin, $ticket, $this->alice);
        $n = $this->alice->notifications()->first();

        $n->data = ['ticket_id' => 1, 'ticket_no' => 'TKT-1', 'title' => 'Old', 'assigned_by' => 'Ada', 'reason' => 'Urgent'];
        $p = NotificationPresenter::present($n);
        $this->assertSame('Ada assigned you TKT-1: Old', $p['text']);
        $this->assertSame('Reason: Urgent', $p['detail']);

        $n->type = 'App\\Notifications\\SomethingElse';
        $this->assertSame('You have a new notification', NotificationPresenter::present($n)['text']);
    }
}
