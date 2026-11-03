<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'ticket_no' => 'TKT-'.fake()->unique()->numerify('T#######'),
            'contact_id' => Contact::factory(),
            // Always the contact's own company, so the data stays consistent.
            'company_id' => fn (array $attrs) => Contact::find($attrs['contact_id'])->company_id,
            'assigned_to' => null,
            'topic' => 'query',
            'priority' => 'medium',
            'status' => 'open',
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'due_at' => null,
        ];
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn () => ['assigned_to' => $user->user_id]);
    }

    public function unassigned(): static
    {
        return $this->state(fn () => ['assigned_to' => null]);
    }

    public function resolved(): static
    {
        return $this->state(fn () => ['status' => 'resolved']);
    }

    public function priority(string $priority): static
    {
        return $this->state(fn () => ['priority' => $priority]);
    }
}
