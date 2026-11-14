<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketAssigned;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells an agent a ticket was assigned to them. Carries only ids: the Ticket global scope reads Auth::user(), which does
 * not exist in a queue worker, so the ticket is loaded here without scopes (system code).
 */
class SendTicketAssignedNotification implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 3;

    public function __construct(
        public int $ticketId,
        public int $agentId,
        public int $assignedById,
        public ?string $reason = null,
    ) {
        $this->onQueue('notifications');
    }

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function uniqueId(): string
    {
        return "assigned:{$this->ticketId}:{$this->agentId}";
    }

    public function handle(): void
    {
        $ticket = Ticket::withoutGlobalScopes()->find($this->ticketId);
        $agent = User::find($this->agentId);
        $assigner = User::find($this->assignedById);

        // Skip when the world changed while the job waited: gone, deactivated, or the ticket moved on to someone else.
        if (! $ticket || ! $agent || ! $assigner || ! $agent->is_active || $ticket->assigned_to !== $this->agentId) {
            return;
        }

        $agent->notify(new TicketAssigned($ticket, $assigner, $this->reason));
    }

    public function failed(Throwable $e): void
    {
        Log::error('TicketAssigned notification failed', [
            'ticket_id' => $this->ticketId,
            'agent_id' => $this->agentId,
            'assigned_by' => $this->assignedById,
            'error' => $e->getMessage(),
        ]);
    }
}
