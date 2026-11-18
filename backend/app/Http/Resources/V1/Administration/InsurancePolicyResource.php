<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Administration;

use App\Models\InsurancePolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InsurancePolicy
 */
class InsurancePolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $days = $this->expiry_date
            ? (int) now()->startOfDay()->diffInDays($this->expiry_date->copy()->startOfDay(), false)
            : null;

        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'insurance_type' => $this->insurance_type,
            'provider' => $this->provider,
            'policy_number' => $this->policy_number,
            'insured_asset' => $this->insured_asset,
            'vehicle_registration' => $this->vehicle_registration,
            'start_date' => $this->start_date?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'premium_amount' => $this->premium_amount,
            'sum_insured' => $this->sum_insured,
            'renewal_status' => $this->renewal_status,
            'status' => $this->status,
            'notes' => $this->notes,
            'days_to_expiry' => $days,
            'is_expired' => $days !== null && $days < 0,
            'has_document' => $this->hasDocument(),
            'document_name' => $this->document_name,
            'responsible_officer_id' => $this->responsible_officer_id,
            'responsible_officer' => $this->whenLoaded('responsibleOfficer', fn () => $this->responsibleOfficer ? [
                'id' => $this->responsibleOfficer->id,
                'full_name' => $this->responsibleOfficer->full_name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
