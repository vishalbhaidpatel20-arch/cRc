<?php

namespace App\Support;

use App\Models\Ticket;
use App\Notifications\TicketAssigned;
use App\Notifications\TicketCompleted;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;

/**
 * One place that turns a stored notification into the text, tag and kind shown in the bell and on /notifications.
 *
 * Agent-only visibility applies here too: a notification keeps a snapshot of the ticket title, but once an agent no
 * longer has the ticket (it was reassigned) they must not read its title any more, so only the ticket number is shown.
 */
class NotificationPresenter
{
    /**
     * Which of these notifications' tickets the current user may still see. Goes through the Ticket global scope, so for
     * an agent it only contains their own tickets; for an admin, every ticket that exists.
     *
     * @param  iterable<DatabaseNotification>  $notifications
     * @return array<int, true> ticket_id => true
     */
    public static function visibleTicketIds(iterable $notifications): array
    {
        // A paginator would be turned into nested arrays by Collection::make(); take its items (the models) instead.
        $items = $notifications instanceof AbstractPaginator ? $notifications->getCollection() : Collection::make($notifications);
        $ids = $items->map(fn ($n) => $n->data['ticket_id'] ?? null)->filter()->unique()->values();

        return $ids->isEmpty() ? [] : Ticket::whereIn('tickets.ticket_id', $ids)->pluck('tickets.ticket_id')->mapWithKeys(fn ($id) => [(int) $id => true])->all();
    }

    /** @return array{text: string, detail: ?string, tag: string, kind: string, visible: bool} */
    public static function present(DatabaseNotification $n, bool $ticketVisible = true): array
    {
        $d = $n->data;
        $ticket = $ticketVisible
            ? trim(($d['ticket_no'] ?? '').': '.($d['title'] ?? ''), ': ')
            : trim($d['ticket_no'] ?? 'a ticket').' (no longer yours)';

        return match ($n->type) {
            TicketAssigned::class => [
                'text' => ($d['assigned_by'] ?? 'An admin').' assigned you '.$ticket,
                'detail' => $ticketVisible && ! empty($d['reason']) ? 'Reason: '.$d['reason'] : null,
                'tag' => 'Assigned',
                'kind' => 'assigned',
                'visible' => $ticketVisible,
            ],
            TicketCompleted::class => [
                'text' => ($d['completed_by'] ?? 'An agent').' completed '.$ticket,
                'detail' => null,
                'tag' => 'Completed',
                'kind' => 'completed',
                'visible' => $ticketVisible,
            ],
            default => ['text' => 'You have a new notification', 'detail' => null, 'tag' => 'Notice', 'kind' => 'other', 'visible' => true],
        };
    }
}
