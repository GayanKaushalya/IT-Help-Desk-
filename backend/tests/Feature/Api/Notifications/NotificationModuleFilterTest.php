<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Notifications;

use App\Enums\RoleSlug;
use App\Models\User;
use App\Models\UserNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationModuleFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }

    public function test_hr_module_filter_hides_it_notifications(): void
    {
        $user = User::factory()->role(RoleSlug::Administrator)->create();

        UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'hr.request.new',
            'title' => 'New HR request',
            'body' => 'HR-2026-000001',
            'data' => ['module' => 'hr', 'hr_request_id' => 1],
        ]);
        UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'ticket.new',
            'title' => 'New ticket',
            'body' => 'IT-2026-000001',
            'data' => ['module' => 'it', 'ticket_id' => 1],
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications?module=hr')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.notifications.0.type', 'hr.request.new');

        $this->getJson('/api/v1/notifications?module=it')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.notifications.0.type', 'ticket.new');
    }
}
