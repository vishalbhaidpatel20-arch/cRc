<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Notifications\Notification;

/** In-app "new assignment" alert (database channel only; email can be added later). */
class TicketAssigned extends Notification
{
    public function __construct(
        public Ticket $ticket,
        public User $assignedBy,
        public ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->ticket_id,
            'ticket_no' => $this->ticket->ticket_no,
            'title' => $this->ticket->title,
            'priority' => $this->ticket->priority,
            'assigned_by' => $this->assignedBy->full_name,
            'reason' => $this->reason,
        ];
    }
}
