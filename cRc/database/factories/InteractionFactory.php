<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Interaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Interaction>
 */
class InteractionFactory extends Factory
{
    protected $model = Interaction::class;

    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'user_id' => User::factory(),
            'ticket_id' => null,
            'topic' => 'general',
            'channel' => 'call',
            'direction' => 'inbound',
            'summary' => fake()->sentence(8),
            'interaction_at' => now()->subHour(),
        ];
    }
}
