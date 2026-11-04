<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Services\EventReminders;
use App\Support\NotificationPresenter;
use App\Support\UserTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Every query goes through $request->user()->notifications(): a user can only ever reach their own. */
class NotificationController extends Controller
{
    /** Full history, newest first. */
    public function index(Request $request, EventReminders $reminders): View
    {
        $reminders->sendDue($request->user());

        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->latest()->latest('id')
                ->paginate(config('crc.per_page'))->withQueryString(),
        ]);
    }

    /**
     * Polled by public/js/notifications.js: the unread count and the bell's list, as ready-made HTML.
     * It also sends this user's calendar reminders that have come due, so they pop up within one poll.
     */
    public function feed(Request $request, EventReminders $reminders): JsonResponse
    {
        $user = $request->user();
        $reminders->sendDue($user);

        $latest = $user->notifications()->latest()->latest('id')->limit(8)->get();
        $newestUnread = $user->unreadNotifications()->latest()->latest('id')->first();
        $visible = $newestUnread ? NotificationPresenter::visibleTicketIds([$newestUnread]) : [];
        $present = $newestUnread ? NotificationPresenter::present($newestUnread, isset($visible[$newestUnread->data['ticket_id'] ?? 0])) : null;

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'newest_unread_id' => $newestUnread?->id,
            'newest_unread_text' => $present['text'] ?? null,
            'newest_unread_kind' => $present['kind'] ?? null,
            'newest_unread_url' => $newestUnread ? route('notifications.open', $newestUnread->id) : null,
            'newest_unread_age' => $newestUnread ? max(0, (int) $newestUnread->created_at->diffInSeconds(now(), true)) : null,
            'html' => view('partials.notification-items', ['items' => $latest])->render(),
        ])->header('Cache-Control', 'no-store');
    }

    /** Marks one notification read and goes to what it is about: the ticket, or the day on the calendar. */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        // A calendar reminder: open that day in the user's own clock.
        if (isset($notification->data['event_id'])) {
            $event = CalendarEvent::where('user_id', $request->user()->user_id)->find($notification->data['event_id']);
            if (! $event) {
                return redirect()->route('calendar.index')->with('status', 'That plan is no longer on your calendar.');
            }
            $local = UserTime::local($event->start_at, UserTime::zone($request));

            return redirect()->route('calendar.index', ['month' => $local->format('Y-m'), 'day' => $local->format('Y-m-d')]);
        }

        $ticketId = $notification->data['ticket_id'] ?? null;
        if (! $ticketId) {
            return redirect()->route('notifications.index');
        }

        // An agent who lost the ticket (it was reassigned) would get a 404: send them to their own list instead.
        if (! isset(NotificationPresenter::visibleTicketIds([$notification])[(int) $ticketId])) {
            return redirect()->route('tickets.index')->with('status', 'That ticket is no longer assigned to you.');
        }

        return redirect()->route('tickets.show', $ticketId);
    }

    /** Marks only the current user's own notifications as read. */
    public function markAllRead(Request $request): RedirectResponse|JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return $request->expectsJson() ? response()->json(['unread' => 0]) : back();
    }
}
