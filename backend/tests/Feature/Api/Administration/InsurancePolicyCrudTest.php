<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Administration;

use App\Enums\RoleSlug;
use App\Models\InsurancePolicy;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InsurancePolicyCrudTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/administration/staff/registers/insurance';

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $this->manager = User::factory()->role(RoleSlug::AdministrationManager)->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Acme Lanka',
            'insurance_type' => 'Motor',
            'provider' => 'Ceylinco',
            'policy_number' => 'POL-1001',
            'insured_asset' => 'Toyota Hiace',
            'vehicle_registration' => 'CAB-1234',
            'start_date' => now()->subMonths(11)->toDateString(),
            'expiry_date' => now()->addMonth()->toDateString(),
            'premium_amount' => 125000.5,
            'renewal_status' => 'not_due',
        ], $overrides);
    }

    public function test_manager_can_create_list_show_update_and_delete_a_policy(): void
    {
        Sanctum::actingAs($this->manager);

        $id = $this->postJson(self::BASE, $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.policy_number', 'POL-1001')
            ->assertJsonPath('data.is_expired', false)
            ->json('data.id');

        $this->assertDatabaseHas('insurance_policies', ['id' => $id, 'provider' => 'Ceylinco', 'insurance_type' => 'Motor']);
        $this->assertDatabaseHas('insurance_policy_history', ['policy_id' => $id, 'event' => 'created']);

        $this->getJson(self::BASE.'?search=CAB-1234')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $id);

        $this->getJson(self::BASE.'?search=nothing-here')->assertOk()->assertJsonPath('meta.total', 0);

        $this->getJson(self::BASE.'/'.$id)->assertOk()->assertJsonPath('data.insured_asset', 'Toyota Hiace');

        $this->patchJson(self::BASE.'/'.$id, [
            'renewal_status' => 'in_progress',
            'responsible_officer_id' => $this->manager->id,
        ])->assertOk()
            ->assertJsonPath('data.renewal_status', 'in_progress')
            ->assertJsonPath('data.responsible_officer.id', $this->manager->id);

        $this->assertDatabaseHas('insurance_policy_history', ['policy_id' => $id, 'event' => 'renewal_status_changed']);

        $this->getJson(self::BASE.'/'.$id.'/history')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->deleteJson(self::BASE.'/'.$id)->assertOk();
        $this->assertSoftDeleted('insurance_policies', ['id' => $id]);
        $this->getJson(self::BASE.'/'.$id)->assertNotFound();
    }

    public function test_policy_number_must_be_unique_and_expiry_after_start(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson(self::BASE, $this->payload())->assertCreated();

        $this->postJson(self::BASE, $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['policy_number']);

        $this->postJson(self::BASE, $this->payload([
            'policy_number' => 'POL-2',
            'start_date' => now()->addDays(10)->toDateString(),
            'expiry_date' => now()->toDateString(),
        ]))->assertUnprocessable()->assertJsonValidationErrors(['expiry_date']);
    }

    public function test_expiry_filters_return_expired_and_expiring_policies(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson(self::BASE, $this->payload(['policy_number' => 'EXPIRED', 'start_date' => null, 'expiry_date' => now()->subDays(5)->toDateString()]))
            ->assertCreated()->assertJsonPath('data.is_expired', true);
        $this->postJson(self::BASE, $this->payload(['policy_number' => 'SOON', 'start_date' => null, 'expiry_date' => now()->addDays(10)->toDateString()]))
            ->assertCreated();
        $this->postJson(self::BASE, $this->payload(['policy_number' => 'LATER', 'start_date' => null, 'expiry_date' => now()->addDays(200)->toDateString()]))
            ->assertCreated();

        $this->getJson(self::BASE.'?expired=1')->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.policy_number', 'EXPIRED');
        $this->getJson(self::BASE.'?expiring_within=30')->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.policy_number', 'SOON');
    }

    public function test_view_only_staff_cannot_write_and_employees_are_blocked(): void
    {
        $officer = User::factory()->role(RoleSlug::AdministrationOfficer)->create();
        $employee = User::factory()->role(RoleSlug::Employee)->create();

        Sanctum::actingAs($officer);
        $this->getJson(self::BASE)->assertOk();
        $this->postJson(self::BASE, $this->payload())->assertForbidden();

        Sanctum::actingAs($employee);
        $this->getJson(self::BASE)->assertForbidden();
    }

    public function test_policy_document_can_be_uploaded_and_downloaded(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->manager);

        $id = $this->postJson(self::BASE, $this->payload())->assertCreated()->json('data.id');

        $this->postJson(self::BASE.'/'.$id.'/document', [
            'document' => UploadedFile::fake()->create('policy.pdf', 50, 'application/pdf'),
        ])->assertOk()->assertJsonPath('data.has_document', true);

        $policy = InsurancePolicy::query()->findOrFail($id);
        Storage::disk('local')->assertExists($policy->document_path);

        $this->get(self::BASE.'/'.$id.'/document')->assertOk();

        $this->postJson(self::BASE.'/'.$id.'/document', [
            'document' => UploadedFile::fake()->create('malware.exe', 10),
        ])->assertUnprocessable();
    }
}
