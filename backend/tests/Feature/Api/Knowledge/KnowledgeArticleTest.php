<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Knowledge;

use App\Enums\RoleSlug;
use App\Models\KnowledgeArticle;
use App\Models\User;
use Database\Seeders\BranchSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KnowledgeArticleTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, BranchSeeder::class, DepartmentSeeder::class]);
        $this->employee = User::factory()->role(RoleSlug::Employee)->create();
        $this->technician = User::factory()->role(RoleSlug::Technician)->create();
    }

    public function test_employees_only_see_published_articles(): void
    {
        KnowledgeArticle::query()->create([
            'slug' => 'reset-password',
            'title' => 'How to reset your Windows password',
            'summary' => 'Self-service steps',
            'body' => 'Use Ctrl+Alt+Delete and choose Change a password.',
            'topic' => 'Accounts',
            'is_published' => true,
            'author_id' => $this->technician->id,
        ]);

        KnowledgeArticle::query()->create([
            'slug' => 'draft-vpn',
            'title' => 'Draft VPN guide',
            'body' => 'This draft should stay hidden from employees.',
            'is_published' => false,
            'author_id' => $this->technician->id,
        ]);

        Sanctum::actingAs($this->employee);

        $this->getJson('/api/v1/knowledge-articles')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'reset-password');
    }

    public function test_a_technician_can_publish_an_article(): void
    {
        Sanctum::actingAs($this->technician);

        $this->postJson('/api/v1/knowledge-articles', [
            'title' => 'Connect to the office Wi-Fi',
            'summary' => 'SSID and first-time setup',
            'body' => 'Choose SSI-Staff, then sign in with your domain account.',
            'topic' => 'Network',
            'is_published' => true,
        ])->assertCreated()
            ->assertJsonPath('data.is_published', true)
            ->assertJsonPath('data.title', 'Connect to the office Wi-Fi');
    }

    public function test_an_employee_cannot_create_articles(): void
    {
        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/knowledge-articles', [
            'title' => 'Should not work',
            'body' => 'Employees cannot publish guides.',
        ])->assertForbidden();
    }
}
