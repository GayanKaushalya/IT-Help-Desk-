<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administration\Staff\Registers;

use App\Models\AdmRequest;
use App\Models\Driver;
use App\Models\FuelCard;
use App\Models\InsurancePolicy;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAllocation;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pick-lists and enumerations for the register forms.
 */
class RegisterLookupController extends AdmRegisterController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeView($request);

        return ApiResponse::success([
            'officers' => User::query()
                ->assignableAdmAgents()
                ->orderBy('full_name')
                ->get(['id', 'full_name'])
                ->map(fn (User $user): array => ['id' => $user->id, 'full_name' => $user->full_name])
                ->all(),
            'vehicles' => Vehicle::query()
                ->orderBy('registration_number')
                ->get(['id', 'registration_number', 'make', 'model', 'status'])
                ->map(fn (Vehicle $vehicle): array => [
                    'id' => $vehicle->id,
                    'registration_number' => $vehicle->registration_number,
                    'label' => trim($vehicle->registration_number.' '.$vehicle->make.' '.$vehicle->model),
                    'status' => $vehicle->status,
                ])
                ->all(),
            'drivers' => Driver::query()
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'status'])
                ->map(fn (Driver $driver): array => [
                    'id' => $driver->id,
                    'full_name' => $driver->full_name,
                    'status' => $driver->status,
                ])
                ->all(),
            'insurance_policies' => InsurancePolicy::query()
                ->orderBy('policy_number')
                ->get(['id', 'policy_number', 'provider'])
                ->map(fn (InsurancePolicy $policy): array => [
                    'id' => $policy->id,
                    'label' => $policy->policy_number.' · '.$policy->provider,
                ])
                ->all(),
            'booking_requests' => AdmRequest::query()
                ->open()
                ->whereHas('category', fn ($category) => $category->where('slug', 'fleet-vehicle-management'))
                ->orderByDesc('id')
                ->limit(50)
                ->get(['id', 'reference', 'subject'])
                ->map(fn (AdmRequest $adm): array => [
                    'id' => $adm->id,
                    'label' => $adm->reference.' · '.$adm->subject,
                ])
                ->all(),
            'companies' => $this->companies(),
            'enums' => [
                'insurance_status' => InsurancePolicy::STATUSES,
                'renewal_status' => InsurancePolicy::RENEWAL_STATUSES,
                'vehicle_status' => Vehicle::STATUSES,
                'driver_status' => Driver::STATUSES,
                'fuel_card_status' => FuelCard::STATUSES,
                'allocation_status' => VehicleAllocation::STATUSES,
            ],
        ]);
    }

    /**
     * Employee picker for fuel-card assignment (search required, capped).
     */
    public function users(Request $request): JsonResponse
    {
        $this->authorizeView($request);

        $term = trim((string) $request->query('search', ''));

        $query = User::query()->active()->orderBy('full_name')->limit(20);

        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], '', $term).'%';
            $query->where(fn ($inner) => $inner->where('full_name', 'like', $like)->orWhere('username', 'like', $like));
        }

        return ApiResponse::success(
            $query->get(['id', 'full_name', 'username'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'username' => $user->username,
                ])
                ->all(),
        );
    }

    /**
     * Distinct company names already used in the registers, for filters.
     *
     * @return list<string>
     */
    private function companies(): array
    {
        return collect([InsurancePolicy::class, Vehicle::class, Driver::class, FuelCard::class])
            ->flatMap(fn (string $model) => $model::query()->whereNotNull('company_name')->distinct()->pluck('company_name'))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
