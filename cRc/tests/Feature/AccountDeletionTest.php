<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\FollowUp;
use App\Models\Interaction;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An admin can delete an agent and an agent can delete themselves. Deleting is a soft delete: the row and its details stay
 * (history keeps its author, an admin can restore it), but the account cannot sign in and leaves every list and picker.
 */
class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $alice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['full_name' => 'Ada Admin']);
        $this->alice = User::factory()->agent()->create(['full_name' => 'Alice Agent', 'email' => 'alice@example.com']);
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user);
    }

    /** Gives Alice some history and some open work. */
    private function giveAliceWork(): array
    {
        $open = Ticket::factory()->assignedTo($this->alice)->create();
        $resolved = Ticket::factory()->assignedTo($this->alice)->resolved()->create();
        $interaction = Interaction::factory()->create(['user_id' => $this->alice->user_id]);
        $followUp = FollowUp::create([
            'contact_id' => $interaction->contact_id, 'interaction_id' => $interaction->interaction_id,
            'assigned_to' => $this->alice->user_id, 'due_date' => now()->addDay()->toDateString(),
            'description' => 'Call back', 'status' => 'pending',
        ]);
        $plan = CalendarEvent::create([
            'user_id' => $this->alice->user_id, 'title' => 'Private plan', 'start_at' => now()->utc()->addDay(),
            'all_day' => false, 'remind_minutes' => null, 'status' => 'planned',
        ]);
        $this->alice->notify(new TicketAssigned($open, $this->admin, null));

        return compact('open', 'resolved', 'interaction', 'followUp', 'plan');
    }

    public function test_admin_deletes_an_agent_and_history_survives(): void
    {
        $w = $this->giveAliceWork();

        $this->as($this->admin)->delete(route('admin.users.destroy', $this->alice))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status');

        // Soft-deleted: hidden from normal queries, but the row keeps its details.
        $this->assertNull(User::find($this->alice->user_id));
        $alice = User::withTrashed()->find($this->alice->user_id);
        $this->assertTrue($alice->trashed());
        $this->assertTrue($alice->isRemoved());
        $this->assertSame('Alice Agent', $alice->full_name);
        $this->assertSame('alice@example.com', $alice->email);
        $this->assertNotNull($alice->password_hash);
        $this->assertFalse((bool) $alice->is_active);

        $this->assertNull(Ticket::withoutGlobalScopes()->find($w['open']->ticket_id)->assigned_to);
        $this->assertSame($this->alice->user_id, Ticket::withoutGlobalScopes()->find($w['resolved']->ticket_id)->assigned_to);
        $this->assertDatabaseHas('ticket_assignments', [
            'ticket_id' => $w['open']->ticket_id, 'from_user' => $alice->user_id, 'to_user' => null,
            'assigned_by' => $this->admin->user_id, 'reason' => 'Agent account deleted',
        ]);
        $this->assertDatabaseHas('interactions', ['interaction_id' => $w['interaction']->interaction_id, 'user_id' => $alice->user_id]);
        $this->assertSame('cancelled', $w['followUp']->fresh()->status);
        // Private plans and notifications are kept (only she could see them), so a restore brings them back.
        $this->assertDatabaseHas('calendar_events', ['event_id' => $w['plan']->event_id]);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_history_still_shows_a_deleted_agent_by_name(): void
    {
        $w = $this->giveAliceWork();
        $this->as($this->admin)->delete(route('admin.users.destroy', $this->alice));

        // Her resolved ticket keeps her as its agent, and the contact's log keeps her as the author.
        $this->as($this->admin)->get(route('tickets.show', $w['resolved']))->assertOk()->assertSee('Alice Agent');
        $this->as($this->admin)->get(route('contacts.show', $w['interaction']->contact_id))->assertOk()->assertSee('Alice Agent');
    }

    public function test_a_deleted_agent_cannot_be_given_a_ticket(): void
    {
        $ticket = Ticket::factory()->create(['assigned_to' => null]);
        $this->as($this->admin)->delete(route('admin.users.destroy', $this->alice));

        $this->as($this->admin)->post(route('tickets.assign', $ticket), ['assigned_to' => $this->alice->user_id])
            ->assertSessionHasErrors('assigned_to');
        $this->assertNull(Ticket::withoutGlobalScopes()->find($ticket->ticket_id)->assigned_to);
    }

    public function test_the_users_page_lists_deleted_agents_with_a_restore_button(): void
    {
        $this->as($this->admin)->get(route('admin.users.index'))->assertOk()->assertSee('No deleted agents.');

        $this->as($this->admin)->delete(route('admin.users.destroy', $this->alice));

        $this->as($this->admin)->get(route('admin.users.index'))->assertOk()
            ->assertSee('id="deleted-user-'.$this->alice->user_id.'"', false)
            ->assertSee(route('admin.users.restore', $this->alice->user_id), false)
            ->assertDontSee('No deleted agents.');
    }

    public function test_admin_restores_a_deleted_agent_who_can_sign_in_again(): void
    {
        $this->as($this->admin)->delete(route('admin.users.destroy', $this->alice));

        $this->as($this->admin)->patch(route('admin.users.restore', $this->alice->user_id))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('highlight', $this->alice->user_id);

        $alice = User::find($this->alice->user_id);   // found again without withTrashed()
        $this->assertNotNull($alice);
        $this->assertNull($alice->deleted_at);
        $this->assertTrue((bool) $alice->is_active);
        $this->as($this->admin)->get(route('admin.users.index'))->assertSee('id="user-'.$alice->user_id.'"', false)
            ->assertSee('No deleted agents.');

        $this->app['auth']->forgetGuards();
        $this->post(route('login'), ['email' => 'alice@example.com', 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($alice);
    }

    public function test_only_admins_restore_and_only_deleted_agents(): void
    {
        $bob = User::factory()->agent()->create();
        $this->as($this->admin)->delete(route('admin.users.destroy', $this->alice));

        // An agent cannot restore anyone (role:admin), and Alice stays deleted.
        $this->as($bob)->patch(route('admin.users.restore', $this->alice->user_id))->assertForbidden();
        $this->assertTrue(User::withTrashed()->find($this->alice->user_id)->trashed());

        // Restoring an agent who is not deleted, or an admin, is a 404.
        $this->as($this->admin)->patch(route('admin.users.restore', $bob))->assertNotFound();
        $this->as($this->admin)->patch(route('admin.users.restore', $this->admin))->assertNotFound();
        $this->as($this->admin)->patch(route('admin.users.restore', 999999))->assertNotFound();
    }

    public function test_a_deleted_agents_email_cannot_be_reused_and_the_message_says_why(): void
    {
        $this->as($this->admin)->delete(route('admin.users.destroy', $this->alice));

        $this->as($this->admin)->post(route('admin.users.store'), [
            'full_name' => 'New Alice', 'email' => 'alice@example.com', 'phone' => null,
            'password' => 'password123', 'password_confirmation' => 'password123', 'all_companies' => 1,
        ])->assertSessionHasErrors(['email' => 'This email is already used, possibly by a deleted agent (deleted accounts keep their address).']);

        $this->assertSame(1, User::withTrashed()->where('email', 'alice@example.com')->count());
    }

    public function test_a_deleted_agent_disappears_from_the_users_list_and_cannot_log_in(): void
    {
        $this->as($this->admin)->delete(route('admin.users.destroy', $this->alice));

        // She leaves the main list (her row id) and shows up only under "Deleted agents".
        $this->as($this->admin)->get(route('admin.users.index'))->assertOk()
            ->assertDontSee('id="user-'.$this->alice->user_id.'"', false)
            ->assertSee('id="deleted-user-'.$this->alice->user_id.'"', false);
        $this->app['auth']->forgetGuards();
        $this->post(route('login'), ['email' => 'alice@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_the_users_list_offers_delete_for_agents_only(): void
    {
        $this->as($this->admin)->get(route('admin.users.index'))
            ->assertSee(route('admin.users.destroy', $this->alice), false)
            ->assertDontSee(route('admin.users.destroy', $this->admin), false);
    }

    public function test_an_admin_cannot_be_deleted_and_agents_cannot_delete_others(): void
    {
        $other = User::factory()->admin()->create();
        $bob = User::factory()->agent()->create();

        $this->as($this->admin)->delete(route('admin.users.destroy', $other))->assertNotFound();
        $this->as($this->admin)->delete(route('admin.users.destroy', $this->admin))->assertNotFound();
        $this->as($this->alice)->delete(route('admin.users.destroy', $bob))->assertForbidden();

        $this->assertFalse($other->fresh()->isRemoved());
        $this->assertFalse($bob->fresh()->isRemoved());
    }

    public function test_deleting_an_already_deleted_agent_is_a_404(): void
    {
        $this->as($this->admin)->delete(route('admin.users.destroy', $this->alice));

        $this->as($this->admin)->delete(route('admin.users.destroy', $this->alice))->assertNotFound();
    }

    public function test_an_agent_deletes_their_own_account_with_their_password(): void
    {
        $w = $this->giveAliceWork();

        $this->as($this->alice)->get(route('account.show'))->assertOk()->assertSee('Delete my account');
        $this->as($this->alice)->delete(route('account.destroy'), ['password' => 'password'])
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertTrue($this->alice->fresh()->isRemoved());
        $this->assertNull(Ticket::withoutGlobalScopes()->find($w['open']->ticket_id)->assigned_to);
        $this->assertDatabaseHas('ticket_assignments', ['ticket_id' => $w['open']->ticket_id, 'assigned_by' => $this->alice->user_id, 'reason' => 'Agent account deleted']);
        $this->assertDatabaseHas('interactions', ['interaction_id' => $w['interaction']->interaction_id]);
    }

    public function test_a_wrong_or_missing_password_deletes_nothing(): void
    {
        $this->as($this->alice)->delete(route('account.destroy'), ['password' => 'nope'])->assertSessionHasErrors('password');
        $this->as($this->alice)->delete(route('account.destroy'))->assertSessionHasErrors('password');

        $this->assertFalse($this->alice->fresh()->isRemoved());
        $this->assertTrue((bool) $this->alice->fresh()->is_active);
    }

    public function test_admins_have_no_self_delete(): void
    {
        $this->as($this->admin)->get(route('account.show'))->assertForbidden();
        $this->as($this->admin)->delete(route('account.destroy'), ['password' => 'password'])->assertForbidden();

        $this->assertFalse($this->admin->fresh()->isRemoved());
    }

    public function test_the_deleted_agents_unassigned_ticket_can_be_assigned_to_someone_else(): void
    {
        $open = Ticket::factory()->assignedTo($this->alice)->create();
        $bob = User::factory()->agent()->create();
        $this->as($this->admin)->delete(route('admin.users.destroy', $this->alice));

        $this->as($this->admin)->post(route('tickets.assign', $open), ['assigned_to' => $bob->user_id])->assertRedirect();

        $this->assertSame($bob->user_id, Ticket::withoutGlobalScopes()->find($open->ticket_id)->assigned_to);
    }
}
