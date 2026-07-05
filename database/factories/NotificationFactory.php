<?php

namespace Database\Factories;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use App\Notifications\NewMessage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        $type = NotificationType::NewMessage;

        return [
            'id' => (string) Str::uuid(),
            'type' => NewMessage::class,
            'notifiable_type' => (new User)->getMorphClass(),
            'notifiable_id' => User::factory(),
            'data' => ['type' => $type->value],
            'read_at' => null,
        ];
    }

    public function ofType(NotificationType $type): static
    {
        return $this->state(fn (): array => ['data' => ['type' => $type->value]]);
    }

    public function read(): static
    {
        return $this->state(fn (): array => ['read_at' => now()]);
    }

    public function unread(): static
    {
        return $this->state(fn (): array => ['read_at' => null]);
    }
}
