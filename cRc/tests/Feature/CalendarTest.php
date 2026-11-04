<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\FollowUp;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\EventReminder;
use App\Services\EventReminders;
use App\Support\NotificationPresenter;
use App\Support\UserTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The calendar: tickets by due date (overdue in red), follow-ups and each user's own plans; plans are private, times are kept in UTC
 * and shown in the viewer's timezone, and a reminder pops up (a notification, then the bell's toast) when its time comes.
 */
class CalendarTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-04 10:00:00');   // a Wednesday; the grid for November 2026 runs Mon 26 Oct to Sun 6 Dec

        $this->admin = User::factory()->admin()->create(['full_name' => 'Ada Admin']);
        $this->alice = User::factory()->agent()->create(['full_name' => 'Alice Agent']);
        $this->bob = User::factory()->agent()->create(['full_name' => 'Bob Agent']);
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user);
    }

    private function plan(User $owner, string $title, string $startUtc, array $over = []): CalendarEvent
    {
        return CalendarEvent::create($over + [
            'user_id' => $owner->user_id, 'title' => $title, 'start_at' => $startUtc,
            'all_day' => false, 'remind_minutes' => null, 'status' => 'planned',
        ]);
    }

    private function payload(array $over = []): array
    {
        return $over + ['title' => 'Call the Bluewave team', 'date' => '2026-11-10', 'time' => '14:30', 'remind_minutes' => 15, 'tz' => 'Asia/Kolkata', '_modal' => 'event-new'];
    }

    // ---- the page ---------------------------------------------------------------------------------------------------

    public function test_the_month_grid_shows_overdue_and_due_tickets_in_the_right_colours_and_days(): void
    {
        $overdue = Ticket::factory()->unassigned()->create(['ticket_no' => 'TKT-0101', 'title' => 'Late one', 'due_at' => '2026-11-02 12:00:00']);
        $later = Ticket::factory()->unassigned()->create(['ticket_no' => 'TKT-0102', 'title' => 'Due soon', 'due_at' => '2026-11-20 09:00:00']);
        Ticket::factory()->unassigned()->resolved()->create(['ticket_no' => 'TKT-0103', 'title' => 'Finished', 'due_at' => '2026-11-03 09:00:00']);
        Ticket::factory()->unassigned()->create(['ticket_no' => 'TKT-0104', 'title' => 'No due date', 'due_at' => null]);

        $r = $this->as($this->admin)->get(route('calendar.index'))->assertOk();
        $html = $r->getContent();

        $r->assertSee('November 2026')->assertSee('Calendar');
        $this->assertMatchesRegularExpression('/<span class="cal-chip overdue">TKT-0101 Late one<\/span>/', $html);
        $this->assertMatchesRegularExpression('/<span class="cal-chip due">TKT-0102 Due soon<\/span>/', $html);
        $this->assertStringNotContainsString('TKT-0103', $html, 'resolved tickets are not on the calendar');
        $this->assertStringNotContainsString('TKT-0104 No due date', $html);

        $this->assertSame(1, $r->viewData('overdueTotal'));
        $this->assertSame('TKT-0101', $r->viewData('overdue')->first()->ticket_no);
        $r->assertSee('Overdue now')->assertDontSee('See all 1 overdue');   // that link only appears when more than 8 are overdue
    }

    public function test_an_agent_only_sees_their_own_tickets_on_the_calendar(): void
    {
        Ticket::factory()->assignedTo($this->alice)->create(['ticket_no' => 'TKT-0201', 'title' => 'Alice work', 'due_at' => '2026-11-05 09:00:00']);
        Ticket::factory()->assignedTo($this->bob)->create(['ticket_no' => 'TKT-0202', 'title' => 'Bob secret', 'due_at' => '2026-11-06 09:00:00']);

        $this->as($this->alice)->get(route('calendar.index'))->assertOk()
            ->assertSee('TKT-0201')->assertDontSee('Bob secret')->assertDontSee('TKT-0202');
        $this->as($this->admin)->get(route('calendar.index'))->assertSee('TKT-0201')->assertSee('TKT-0202');
    }

    public function test_the_selected_day_agenda_lists_plans_tickets_and_follow_ups(): void
    {
        $this->plan($this->alice, 'Prepare the demo', '2026-11-12 04:30:00', ['remind_minutes' => 30]);   // 10:00 in Kolkata
        $this->plan($this->alice, 'Whole day workshop', '2026-11-12 03:30:00', ['all_day' => true]);
        Ticket::factory()->assignedTo($this->alice)->create(['ticket_no' => 'TKT-0301', 'title' => 'Due on the 12th', 'due_at' => '2026-11-12 16:00:00']);
        FollowUp::create(['contact_id' => Contact::factory()->create(['full_name' => 'Dora Contact'])->contact_id, 'assigned_to' => $this->alice->user_id,
            'due_date' => '2026-11-12', 'description' => 'Send the quote', 'status' => 'pending']);

        $r = $this->as($this->alice)->withUnencryptedCookie('crc_tz', 'Asia/Kolkata')
            ->get(route('calendar.index', ['month' => '2026-11', 'day' => '2026-11-12']))->assertOk();

        $r->assertSee('Thursday, 12 November')->assertSee('Prepare the demo')->assertSee('10:00')->assertSee('All day')
            ->assertSee('Whole day workshop')->assertSee('TKT-0301')->assertSee('Dora Contact')->assertSee('Send the quote')
            ->assertSee('30 minutes before');
        $this->assertStringContainsString('data-region="cal-agenda"', $r->getContent());
    }

    public function test_bad_month_and_day_parameters_fall_back_safely(): void
    {
        $this->as($this->alice)->get(route('calendar.index', ['month' => 'nonsense', 'day' => '2026-02-31']))->assertOk()->assertSee('November 2026');
        $this->get(route('calendar.index', ['month' => '2026-13']))->assertOk()->assertSee('November 2026');
        $this->get(route('calendar.index', ['month' => '2027-02', 'day' => '2026-01-01']))->assertOk()->assertSee('February 2027');   // day outside the grid
        $this->get(route('calendar.index', ['month' => '2026-12']))->assertOk()->assertSee('December 2026')->assertSee('1 December');
    }

    public function test_the_calendar_is_an_icon_beside_the_bell_not_a_text_link_in_the_menu(): void
    {
        foreach ([$this->admin, $this->alice] as $user) {
            $html = $this->as($user)->get(route($user->homeRoute()))->assertOk()->getContent();

            // An icon link with a label and tooltip, placed in the right-hand group before the bell.
            $this->assertMatchesRegularExpression('/<a class="bell-btn cal-btn\s*" href="'.preg_quote(route('calendar.index'), '/').'" title="Calendar" aria-label="Calendar">/', $html);
            $this->assertLessThan(strpos($html, 'data-bell-toggle'), strpos($html, 'cal-btn'), 'the calendar icon comes before the bell');

            // The text menu no longer has it.
            $nav = substr($html, strpos($html, '<nav>'), strpos($html, '</nav>') - strpos($html, '<nav>'));
            $this->assertStringNotContainsString('calendar', strtolower($nav));
        }

        // On the calendar page the icon is marked active.
        $this->as($this->alice)->get(route('calendar.index'))->assertSee('cal-btn active', false);
    }

    // ---- planning ---------------------------------------------------------------------------------------------------

    public function test_a_plan_is_stored_in_utc_from_the_users_own_clock(): void
    {
        $this->as($this->alice)->post(route('calendar.events.store'), $this->payload())
            ->assertRedirect(route('calendar.index', ['month' => '2026-11', 'day' => '2026-11-10']));

        $e = CalendarEvent::firstOrFail();
        $this->assertSame($this->alice->user_id, $e->user_id);
        $this->assertSame('2026-11-10 09:00:00', $e->start_at->format('Y-m-d H:i:s'), '14:30 in Kolkata is 09:00 UTC');
        $this->assertSame(15, $e->remind_minutes);
        $this->assertSame('planned', $e->status);
        $this->assertNull($e->reminded_at, 'a reminder for the future is still to come');

        // Shown back in the same clock.
        $this->withUnencryptedCookie('crc_tz', 'Asia/Kolkata')->get(route('calendar.index', ['month' => '2026-11', 'day' => '2026-11-10']))
            ->assertSee('Call the Bluewave team')->assertSee('14:30');
    }

    public function test_a_plan_saves_with_the_alias_zone_chrome_reports_and_even_with_a_bad_zone(): void
    {
        $this->as($this->alice)->post(route('calendar.events.store'), $this->payload(['tz' => 'Asia/Calcutta']))->assertSessionHasNoErrors();
        $this->assertSame('2026-11-10 09:00:00', CalendarEvent::firstOrFail()->start_at->format('Y-m-d H:i:s'), 'the alias is the same clock as Kolkata');

        // A zone the server does not know never blocks saving: it falls back to the app's (UTC).
        $this->post(route('calendar.events.store'), $this->payload(['title' => 'Bad zone', 'tz' => 'Mars/Olympus']))->assertSessionHasNoErrors();
        $this->assertSame('2026-11-10 14:30:00', CalendarEvent::where('title', 'Bad zone')->firstOrFail()->start_at->format('Y-m-d H:i:s'));
    }

    public function test_the_all_day_option_ignores_the_time_and_uses_nine_in_the_morning(): void
    {
        $this->as($this->admin)->post(route('calendar.events.store'), $this->payload(['all_day' => '1', 'time' => '23:59', 'remind_minutes' => 0]));

        $e = CalendarEvent::firstOrFail();
        $this->assertTrue($e->all_day);
        $this->assertSame('2026-11-10 03:30:00', $e->start_at->format('Y-m-d H:i:s'), '09:00 in Kolkata');
    }

    public function test_the_browsers_zone_can_come_from_the_cookie_and_a_bad_zone_falls_back_to_utc(): void
    {
        $request = fn (array $input = [], array $cookies = []) => \Illuminate\Http\Request::create('/', 'GET', $input, $cookies);

        $this->assertSame('Asia/Kolkata', UserTime::zone($request([], ['crc_tz' => 'Asia/Kolkata'])));
        $this->assertSame('America/New_York', UserTime::zone($request(['tz' => 'America/New_York'], ['crc_tz' => 'Asia/Kolkata'])), 'the form field wins');
        $this->assertSame('Asia/Calcutta', UserTime::zone($request([], ['crc_tz' => 'Asia/Calcutta'])), 'Chrome reports the old alias for Kolkata');
        $this->assertSame('UTC', UserTime::zone($request(['tz' => 'Mars/Olympus'], ['crc_tz' => 'also-bad'])));
        $this->assertSame('UTC', UserTime::zone($request()));
    }

    public function test_the_plan_form_is_validated_and_a_failed_submit_reopens_the_popup_with_the_errors(): void
    {
        $this->as($this->alice)->from(route('calendar.index'))->post(route('calendar.events.store'), $this->payload(['title' => '']))->assertSessionHasErrors('title');
        $this->post(route('calendar.events.store'), $this->payload(['date' => 'tomorrow']))->assertSessionHasErrors('date');
        $this->post(route('calendar.events.store'), $this->payload(['time' => '25:99']))->assertSessionHasErrors('time');
        $this->post(route('calendar.events.store'), $this->payload(['remind_minutes' => 7]))->assertSessionHasErrors('remind_minutes');
        $this->post(route('calendar.events.store'), $this->payload(['title' => str_repeat('x', 151)]))->assertSessionHasErrors('title');
        $this->assertSame(0, CalendarEvent::count());

        $html = $this->from(route('calendar.index'))->followingRedirects()->post(route('calendar.events.store'), $this->payload(['title' => '', 'notes' => 'Keep this text']))->getContent();
        $this->assertStringContainsString('The title field is required.', $html);
        $this->assertMatchesRegularExpression('/<dialog id="event-modal"[^>]*data-open/', $html);
        $this->assertStringContainsString('Keep this text', $html, 'what was typed is kept');
    }

    public function test_a_plan_can_be_edited_ticked_off_reopened_and_deleted(): void
    {
        $e = $this->plan($this->alice, 'Old title', '2026-11-10 09:00:00', ['remind_minutes' => 15]);

        $this->as($this->alice)->put(route('calendar.events.update', $e), $this->payload(['title' => 'New title', 'date' => '2026-11-11', 'time' => '10:00', 'remind_minutes' => '', '_modal' => 'event-edit-'.$e->event_id]))
            ->assertRedirect(route('calendar.index', ['month' => '2026-11', 'day' => '2026-11-11']));
        $e->refresh();
        $this->assertSame('New title', $e->title);
        $this->assertSame('2026-11-11 04:30:00', $e->start_at->format('Y-m-d H:i:s'));
        $this->assertNull($e->remind_minutes, 'an empty reminder means none');

        $this->patch(route('calendar.events.done', $e))->assertRedirect();
        $this->assertSame('done', $e->fresh()->status);
        $this->patch(route('calendar.events.done', $e))->assertRedirect();
        $this->assertSame('planned', $e->fresh()->status);

        $this->withUnencryptedCookie('crc_tz', 'Asia/Kolkata')->delete(route('calendar.events.destroy', $e))
            ->assertRedirect(route('calendar.index', ['month' => '2026-11', 'day' => '2026-11-11']));
        $this->assertSame(0, CalendarEvent::count());
    }

    public function test_editing_the_title_does_not_re_send_a_reminder_but_moving_the_plan_does(): void
    {
        $e = $this->plan($this->alice, 'Standup', now()->addMinutes(10)->format('Y-m-d H:i:s'), ['remind_minutes' => 15]);
        $this->assertSame(1, app(EventReminders::class)->sendDue($this->alice));
        $local = UserTime::local($e->fresh()->start_at, 'UTC');

        // Same time, same reminder, new title: still marked as sent.
        $this->as($this->alice)->put(route('calendar.events.update', $e), $this->payload([
            'title' => 'Standup (renamed)', 'date' => $local->format('Y-m-d'), 'time' => $local->format('H:i'), 'remind_minutes' => 15, 'tz' => 'UTC',
        ]))->assertSessionHasNoErrors();
        $this->assertNotNull($e->fresh()->reminded_at);
        $this->assertSame(0, app(EventReminders::class)->sendDue($this->alice));

        // Moved to a later time (still ahead): the reminder is armed again.
        $later = $local->addHours(2);
        $this->put(route('calendar.events.update', $e), $this->payload([
            'date' => $later->format('Y-m-d'), 'time' => $later->format('H:i'), 'remind_minutes' => 15, 'tz' => 'UTC',
        ]))->assertSessionHasNoErrors();
        $this->assertNull($e->fresh()->reminded_at);
    }

    public function test_plans_are_private_nobody_can_see_or_change_someone_elses(): void
    {
        $mine = $this->plan($this->alice, 'Alices private plan', '2026-11-10 09:00:00');

        // Not on anyone else's calendar, not even an admin's.
        $this->as($this->bob)->get(route('calendar.index', ['month' => '2026-11', 'day' => '2026-11-10']))->assertOk()->assertDontSee('Alices private plan');
        $this->as($this->admin)->get(route('calendar.index', ['month' => '2026-11', 'day' => '2026-11-10']))->assertOk()->assertDontSee('Alices private plan');

        // And not reachable: it looks like it does not exist.
        foreach ([$this->bob, $this->admin] as $intruder) {
            $this->as($intruder)->put(route('calendar.events.update', $mine), $this->payload(['title' => 'Hacked']))->assertNotFound();
            $this->patch(route('calendar.events.done', $mine))->assertNotFound();
            $this->delete(route('calendar.events.destroy', $mine))->assertNotFound();
        }
        $mine->refresh();
        $this->assertSame('Alices private plan', $mine->title);
        $this->assertSame('planned', $mine->status);
    }

    // ---- reminders ----------------------------------------------------------------------------------------------------

    public function test_a_reminder_is_sent_once_when_its_time_comes_and_only_to_the_owner(): void
    {
        $soon = $this->plan($this->alice, 'Stand-up with the team', now()->addMinutes(10)->format('Y-m-d H:i:s'), ['remind_minutes' => 15]);
        $later = $this->plan($this->alice, 'Next week', now()->addDays(7)->format('Y-m-d H:i:s'), ['remind_minutes' => 15]);
        $bobs = $this->plan($this->bob, 'Bobs plan', now()->addMinutes(5)->format('Y-m-d H:i:s'), ['remind_minutes' => 0]);

        $sent = app(EventReminders::class)->sendDue($this->alice);

        $this->assertSame(1, $sent);
        $n = $this->alice->notifications()->first();
        $this->assertSame(EventReminder::class, $n->type);
        $this->assertSame($soon->event_id, $n->data['event_id']);
        $this->assertNotNull($soon->fresh()->reminded_at);
        $this->assertNull($later->fresh()->reminded_at, 'not due yet');
        $this->assertNull($bobs->fresh()->reminded_at, 'only this user was checked');
        $this->assertCount(0, $this->bob->notifications);

        $this->assertSame(0, app(EventReminders::class)->sendDue($this->alice), 'never twice');
        $this->assertCount(1, $this->alice->fresh()->notifications);

        // The reminder time itself arrives: Next week's plan fires 15 minutes before it starts.
        $this->travelTo(now()->addDays(7)->subMinutes(10));
        $this->assertSame(1, app(EventReminders::class)->sendDue(), 'Alices next-week plan fires; Bobs old one is a week stale, so it stays quiet');
        $this->assertCount(0, $this->bob->fresh()->notifications);
    }

    public function test_done_plans_and_plans_without_a_reminder_never_send_one_and_stale_ones_stay_quiet(): void
    {
        $this->plan($this->alice, 'Done already', now()->addMinutes(5)->format('Y-m-d H:i:s'), ['remind_minutes' => 15, 'status' => 'done']);
        $this->plan($this->alice, 'No reminder set', now()->addMinutes(5)->format('Y-m-d H:i:s'), ['remind_minutes' => null]);
        $stale = $this->plan($this->alice, 'Long gone', now()->subHours(8)->format('Y-m-d H:i:s'), ['remind_minutes' => 15]);

        $this->assertSame(0, app(EventReminders::class)->sendDue());
        $this->assertCount(0, $this->alice->notifications);
        $this->assertNotNull($stale->fresh()->reminded_at, 'a stale one is marked, so it is not looked at again');
    }

    public function test_planning_something_already_in_the_past_does_not_fire_a_reminder_straight_away(): void
    {
        $this->as($this->alice)->post(route('calendar.events.store'), $this->payload(['date' => '2026-11-03', 'time' => '09:00', 'tz' => 'UTC']));

        $e = CalendarEvent::firstOrFail();
        $this->assertNotNull($e->reminded_at);
        $this->assertSame(0, app(EventReminders::class)->sendDue());
    }

    public function test_the_bell_poll_sends_due_reminders_and_returns_the_popup_details(): void
    {
        $this->plan($this->alice, 'Review the report', now()->addMinutes(12)->format('Y-m-d H:i:s'), ['remind_minutes' => 15]);
        $this->plan($this->bob, 'Bobs reminder', now()->addMinutes(2)->format('Y-m-d H:i:s'), ['remind_minutes' => 15]);

        $feed = $this->as($this->alice)->getJson(route('notifications.feed'))->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('newest_unread_kind', 'reminder')
            ->assertJsonPath('newest_unread_text', 'Reminder: Review the report');

        $n = $this->alice->notifications()->first();
        $feed->assertJsonPath('newest_unread_url', route('notifications.open', $n->id));
        $this->assertLessThanOrEqual(5, $feed->json('newest_unread_age'), 'it just came due, so the first poll can pop it up');
        $this->assertStringContainsString('Review the report', $feed->json('html'));
        $this->assertCount(0, $this->bob->notifications, 'polling only sends the caller their own');

        // Polling again does not repeat it.
        $this->getJson(route('notifications.feed'))->assertJsonPath('unread', 1);
        $this->assertCount(1, $this->alice->fresh()->notifications);

        // The full list page also looks for due reminders.
        $this->as($this->bob)->get(route('notifications.index'))->assertOk()->assertSee('Reminder: Bobs reminder');
    }

    public function test_the_scheduled_command_sends_reminders_for_everyone(): void
    {
        $this->plan($this->alice, 'Alice thing', now()->addMinutes(5)->format('Y-m-d H:i:s'), ['remind_minutes' => 15]);
        $this->plan($this->admin, 'Admin thing', now()->addMinutes(5)->format('Y-m-d H:i:s'), ['remind_minutes' => 15]);

        $this->artisan('crc:send-reminders')->expectsOutput('2 reminder(s) sent.')->assertSuccessful();

        $this->assertCount(1, $this->alice->notifications);
        $this->assertCount(1, $this->admin->notifications);
        $this->artisan('crc:send-reminders')->expectsOutput('0 reminder(s) sent.')->assertSuccessful();
    }

    public function test_opening_a_reminder_goes_to_that_day_in_the_users_own_clock(): void
    {
        $e = $this->plan($this->alice, 'Late night call', '2026-11-10 21:00:00', ['remind_minutes' => 0]);   // 02:30 on the 11th in Kolkata
        $this->travelTo('2026-11-10 21:00:00');
        app(EventReminders::class)->sendDue($this->alice);
        $n = $this->alice->notifications()->first();

        $this->as($this->alice)->withUnencryptedCookie('crc_tz', 'Asia/Kolkata')->get(route('notifications.open', $n->id))
            ->assertRedirect(route('calendar.index', ['month' => '2026-11', 'day' => '2026-11-11']));
        $this->assertNotNull($n->fresh()->read_at);

        // The plan was deleted meanwhile: land on the calendar with a note instead of an error.
        $e->delete();
        $n2 = $this->alice->notifications()->first();
        $this->get(route('notifications.open', $n2->id))->assertRedirect(route('calendar.index'))->assertSessionHas('status');
    }

    public function test_the_presenter_words_reminders_for_timed_and_all_day_plans(): void
    {
        $timed = $this->plan($this->alice, 'Quarterly review', now()->addMinutes(15)->format('Y-m-d H:i:s'), ['remind_minutes' => 15]);
        $allDay = $this->plan($this->alice, 'Training day', now()->format('Y-m-d H:i:s'), ['all_day' => true, 'remind_minutes' => 0]);
        app(EventReminders::class)->sendDue($this->alice);

        $byEvent = $this->alice->notifications->keyBy(fn ($n) => $n->data['event_id']);

        $p = NotificationPresenter::present($byEvent[$timed->event_id]);
        $this->assertSame('Reminder: Quarterly review', $p['text']);
        $this->assertSame('15 minutes from now', $p['detail']);
        $this->assertSame(['Reminder', 'reminder'], [$p['tag'], $p['kind']]);

        $this->assertSame('All day today', NotificationPresenter::present($byEvent[$allDay->event_id])['detail']);
    }
}
