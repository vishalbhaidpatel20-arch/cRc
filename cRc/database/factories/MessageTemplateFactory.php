<?php

namespace Database\Factories;

use App\Models\MessageTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageTemplate>
 */
class MessageTemplateFactory extends Factory
{
    protected $model = MessageTemplate::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'category' => 'general',
            'channel' => 'email',
            'subject' => 'Update on {{ticket_no}}',
            'body' => 'Hello {{name}}, this is {{agent_name}} about {{ticket_no}}.',
            'is_active' => true,
        ];
    }

    public function whatsapp(): static
    {
        return $this->state(fn () => ['channel' => 'whatsapp', 'subject' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
