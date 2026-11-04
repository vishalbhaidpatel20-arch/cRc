<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Notifications\Notification;

/** In-app alert for the admin who handed out a ticket: the agent marked it resolved (database channel only). */
class TicketCompleted extends Notification
{
    public function __construct(
        public Ticket $ticket,
        public User $completedBy,
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
            'completed_by' => $this->completedBy->full_name,
        ];
    }
}
