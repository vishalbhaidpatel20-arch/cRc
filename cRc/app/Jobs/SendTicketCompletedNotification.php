<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\User;
use App\Notifications\TicketCompleted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Tells the admin who handed out a ticket that the agent resolved it. The recipient is the admin who made the latest
 * assignment to that agent; if that admin is gone or inactive (or the agent created the ticket), every active admin.
 */
class SendTicketCompletedNotification implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 3;

    public function __construct(
        public int $ticketId,
        public int $agentId,
    ) {
        $this->onQueue('notifications');
    }

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function uniqueId(): string
    {
        return "completed:{$this->ticketId}";
    }

    public function handle(): void
    {
        $ticket = Ticket::withoutGlobalScopes()->find($this->ticketId);
        $agent = User::withTrashed()->find($this->agentId);   // the work was done even if the agent was deleted since

        if (! $ticket || ! $agent) {
            return;
        }

        $assigner = TicketAssignment::where('ticket_id', $ticket->ticket_id)
            ->where('to_user', $agent->user_id)
            ->orderByDesc('assigned_at')->orderByDesc('assignment_id')
            ->first()?->assignedBy;

        $recipients = ($assigner && $assigner->isAdmin() && $assigner->isActive())
            ? collect([$assigner])
            : User::where('role', User::ROLE_ADMIN)->active()->get();

        Notification::send($recipients, new TicketCompleted($ticket, $agent));
    }

    public function failed(Throwable $e): void
    {
        Log::error('TicketCompleted notification failed', [
            'ticket_id' => $this->ticketId,
            'agent_id' => $this->agentId,
            'error' => $e->getMessage(),
        ]);
    }
}
