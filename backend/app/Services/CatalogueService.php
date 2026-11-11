<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CatalogueService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return array{
     *     categories: list<TicketCategory>,
     *     priorities: list<TicketPriority>,
     *     statuses: list<TicketStatus>
     * }
     */
    public function overview(): array
    {
        return [
            'categories' => TicketCategory::query()->with('defaultPriority')->ordered()->get()->all(),
            'priorities' => TicketPriority::query()->ordered()->get()->all(),
            'statuses' => TicketStatus::query()->ordered()->get()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createCategory(User $actor, array $data): TicketCategory
    {
        $category = TicketCategory::query()->create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug(TicketCategory::class, (string) $data['name'], $data['slug'] ?? null),
            'description' => $data['description'] ?? null,
            'default_priority_id' => $data['default_priority_id'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'requires_item_details' => (bool) ($data['requires_item_details'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        $this->audit->log(
            event: 'category.created',
            subject: $category,
            newValues: $category->only(['name', 'slug', 'is_active']),
            description: $actor->username.' created category '.$category->name,
        );

        return $category->load('defaultPriority');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCategory(User $actor, TicketCategory $category, array $data): TicketCategory
    {
        $before = $category->only([
            'name',
            'description',
            'default_priority_id',
            'is_active',
            'requires_item_details',
            'sort_order',
        ]);

        $category->fill([
            'name' => $data['name'] ?? $category->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $category->description,
            'default_priority_id' => array_key_exists('default_priority_id', $data)
                ? $data['default_priority_id']
                : $category->default_priority_id,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $category->is_active,
            'requires_item_details' => array_key_exists('requires_item_details', $data)
                ? (bool) $data['requires_item_details']
                : $category->requires_item_details,
            'sort_order' => array_key_exists('sort_order', $data) ? (int) $data['sort_order'] : $category->sort_order,
        ])->save();

        $this->audit->log(
            event: 'category.updated',
            subject: $category,
            oldValues: $before,
            newValues: $category->only(['name', 'description', 'default_priority_id', 'is_active', 'sort_order']),
            description: $actor->username.' updated category '.$category->name,
        );

        return $category->load('defaultPriority');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPriority(User $actor, array $data): TicketPriority
    {
        $priority = TicketPriority::query()->create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug(TicketPriority::class, (string) $data['name'], $data['slug'] ?? null),
            'level' => (int) $data['level'],
            'color_token' => $data['color_token'],
            'response_minutes' => $data['response_minutes'] ?? null,
            'resolution_minutes' => $data['resolution_minutes'] ?? null,
            'is_default' => false,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        $this->audit->log(
            event: 'priority.created',
            subject: $priority,
            newValues: $priority->only(['name', 'slug', 'level']),
            description: $actor->username.' created priority '.$priority->name,
        );

        return $priority;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePriority(User $actor, TicketPriority $priority, array $data): TicketPriority
    {
        if (($data['is_active'] ?? true) === false && $priority->is_default) {
            throw new ApiException('The default priority cannot be deactivated.', Response::HTTP_UNPROCESSABLE_ENTITY, 'DEFAULT_LOCKED');
        }

        $before = $priority->only(['name', 'level', 'color_token', 'response_minutes', 'resolution_minutes', 'is_active', 'sort_order']);

        $priority->fill([
            'name' => $data['name'] ?? $priority->name,
            'level' => array_key_exists('level', $data) ? (int) $data['level'] : $priority->level,
            'color_token' => $data['color_token'] ?? $priority->color_token,
            'response_minutes' => array_key_exists('response_minutes', $data) ? $data['response_minutes'] : $priority->response_minutes,
            'resolution_minutes' => array_key_exists('resolution_minutes', $data) ? $data['resolution_minutes'] : $priority->resolution_minutes,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $priority->is_active,
            'sort_order' => array_key_exists('sort_order', $data) ? (int) $data['sort_order'] : $priority->sort_order,
        ])->save();

        $this->audit->log(
            event: 'priority.updated',
            subject: $priority,
            oldValues: $before,
            newValues: $priority->only(['name', 'level', 'color_token', 'is_active', 'sort_order']),
            description: $actor->username.' updated priority '.$priority->name,
        );

        return $priority;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateStatus(User $actor, TicketStatus $status, array $data): TicketStatus
    {
        if (($data['is_active'] ?? true) === false && $status->is_default) {
            throw new ApiException('The default status cannot be deactivated.', Response::HTTP_UNPROCESSABLE_ENTITY, 'DEFAULT_LOCKED');
        }

        $before = $status->only(['name', 'color_token', 'is_active', 'sort_order']);

        $status->fill([
            'name' => $data['name'] ?? $status->name,
            'color_token' => $data['color_token'] ?? $status->color_token,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $status->is_active,
            'sort_order' => array_key_exists('sort_order', $data) ? (int) $data['sort_order'] : $status->sort_order,
        ])->save();

        $this->audit->log(
            event: 'status.updated',
            subject: $status,
            oldValues: $before,
            newValues: $status->only(['name', 'color_token', 'is_active', 'sort_order']),
            description: $actor->username.' updated status '.$status->name,
        );

        return $status;
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function uniqueSlug(string $model, string $name, ?string $slug): string
    {
        $base = Str::slug($slug ?: $name);
        $base = $base !== '' ? $base : 'item';
        $candidate = $base;
        $suffix = 2;

        while ($model::query()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
