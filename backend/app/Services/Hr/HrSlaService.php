<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\HrCategory;
use App\Models\HrRequestType;
use App\Models\HrSlaSetting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Read/update HR SLA targets used for due dates and escalation.
 */
class HrSlaService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function catalogue(): array
    {
        return HrCategory::query()
            ->ordered()
            ->with(['requestTypes' => fn ($types) => $types->ordered()])
            ->get()
            ->map(function (HrCategory $category) {
                $categorySetting = $this->categorySetting($category->id);

                return [
                    'id' => $category->id,
                    'slug' => $category->slug,
                    'name' => $category->name,
                    'is_confidential' => $category->is_confidential,
                    'default_sla_hours' => $category->default_sla_hours,
                    'target_hours' => $categorySetting?->target_hours ?? $category->default_sla_hours,
                    'escalate_to_manager' => $categorySetting?->escalate_to_manager ?? true,
                    'types' => $category->requestTypes->map(function (HrRequestType $type) use ($category) {
                        $typeSetting = $this->typeSetting($category->id, $type->id);

                        return [
                            'id' => $type->id,
                            'slug' => $type->slug,
                            'name' => $type->name,
                            'sla_hours' => $type->sla_hours,
                            'target_hours' => $typeSetting?->target_hours ?? $type->sla_hours ?? $category->default_sla_hours,
                            'escalate_to_manager' => $typeSetting?->escalate_to_manager ?? true,
                            'default_referral_department' => $type->default_referral_department,
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array{target_hours: int, escalate_to_manager?: bool}  $data
     */
    public function updateCategory(HrCategory $category, array $data, ?User $actor = null): HrSlaSetting
    {
        $hours = $this->validatedHours($data['target_hours'] ?? null);
        $escalate = array_key_exists('escalate_to_manager', $data)
            ? (bool) $data['escalate_to_manager']
            : true;

        $setting = HrSlaSetting::updateOrCreate(
            [
                'hr_category_id' => $category->id,
                'hr_request_type_id' => null,
            ],
            [
                'target_hours' => $hours,
                'escalate_to_manager' => $escalate,
                'is_active' => true,
            ],
        );

        $category->forceFill(['default_sla_hours' => $hours])->save();

        if ($actor) {
            $this->audit->log(
                event: 'hr_sla.category_updated',
                subject: $category,
                newValues: ['target_hours' => $hours, 'escalate_to_manager' => $escalate],
                description: $actor->username.' updated SLA for '.$category->name,
            );
        }

        return $setting;
    }

    /**
     * @param  array{target_hours: int, escalate_to_manager?: bool}  $data
     */
    public function updateType(HrRequestType $type, array $data, ?User $actor = null): HrSlaSetting
    {
        $hours = $this->validatedHours($data['target_hours'] ?? null);
        $escalate = array_key_exists('escalate_to_manager', $data)
            ? (bool) $data['escalate_to_manager']
            : true;

        $setting = HrSlaSetting::updateOrCreate(
            [
                'hr_category_id' => $type->hr_category_id,
                'hr_request_type_id' => $type->id,
            ],
            [
                'target_hours' => $hours,
                'escalate_to_manager' => $escalate,
                'is_active' => true,
            ],
        );

        $type->forceFill(['sla_hours' => $hours])->save();

        if ($actor) {
            $this->audit->log(
                event: 'hr_sla.type_updated',
                subject: $type,
                newValues: ['target_hours' => $hours, 'escalate_to_manager' => $escalate],
                description: $actor->username.' updated SLA for '.$type->name,
            );
        }

        return $setting;
    }

    private function validatedHours(mixed $hours): int
    {
        $hours = (int) $hours;

        if ($hours < 1 || $hours > 24 * 30) {
            throw ValidationException::withMessages([
                'target_hours' => ['Target hours must be between 1 and 720.'],
            ]);
        }

        return $hours;
    }

    private function categorySetting(int $categoryId): ?HrSlaSetting
    {
        return HrSlaSetting::query()
            ->where('hr_category_id', $categoryId)
            ->whereNull('hr_request_type_id')
            ->where('is_active', true)
            ->first();
    }

    private function typeSetting(int $categoryId, int $typeId): ?HrSlaSetting
    {
        return HrSlaSetting::query()
            ->where('hr_category_id', $categoryId)
            ->where('hr_request_type_id', $typeId)
            ->where('is_active', true)
            ->first();
    }
}
