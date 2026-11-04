<?php

namespace App\Http\Controllers;

use App\Support\NotificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Every query goes through $request->user()->notifications(): a user can only ever reach their own. */
class NotificationController extends Controller
{
    /** Full history, newest first. */
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->latest()->latest('id')
                ->paginate(config('crc.per_page'))->withQueryString(),
        ]);
    }

    /** Polled by public/js/notifications.js: the unread count and the bell's list, as ready-made HTML. */
    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();
        $latest = $user->notifications()->latest()->latest('id')->limit(8)->get();
        $newestUnread = $user->unreadNotifications()->latest()->latest('id')->first();
        $visible = $newestUnread ? NotificationPresenter::visibleTicketIds([$newestUnread]) : [];

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'newest_unread_id' => $newestUnread?->id,
            'newest_unread_text' => $newestUnread
                ? NotificationPresenter::present($newestUnread, isset($visible[$newestUnread->data['ticket_id'] ?? 0]))['text']
                : null,
            'html' => view('partials.notification-items', ['items' => $latest])->render(),
        ])->header('Cache-Control', 'no-store');
    }

    /** Marks one notification read and goes to the ticket it is about. */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

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
