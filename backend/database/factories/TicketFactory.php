<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use RuntimeException;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => $this->uniqueReference(),
            'subject' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'requester_id' => User::factory(),
            'branch_id' => fn (array $attributes) => User::query()->find($attributes['requester_id'])?->branch_id
                ?? Branch::factory()->create()->id,
            'category_id' => fn () => $this->lookupId(TicketCategory::class, 'hardware', 'Hardware'),
            'priority_id' => fn () => $this->lookupId(TicketPriority::class, 'medium', 'Medium'),
            'status_id' => fn () => $this->statusId('new'),
            'source' => 'web',
        ];
    }

    public function status(string $slug): static
    {
        return $this->state(fn () => ['status_id' => $this->statusId($slug)]);
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn () => [
            'assigned_to' => $user->id,
            'assigned_at' => now(),
        ]);
    }

    public function forRequester(User $user): static
    {
        return $this->state(fn () => [
            'requester_id' => $user->id,
            'branch_id' => $user->branch_id,
        ]);
    }

    private function uniqueReference(): string
    {
        return 'TKT-'.now()->year.'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function statusId(string $slug): int
    {
        $status = TicketStatus::query()->where('slug', $slug)->first();

        if (! $status) {
            throw new RuntimeException("Ticket status [{$slug}] has not been seeded.");
        }

        return $status->id;
    }

    /**
     * @param  class-string  $model
     */
    private function lookupId(string $model, string $slug, string $name): int
    {
        $record = $model::query()->where('slug', $slug)->first();

        if (! $record) {
            throw new RuntimeException("{$name} [{$slug}] has not been seeded.");
        }

        return $record->id;
    }
}
