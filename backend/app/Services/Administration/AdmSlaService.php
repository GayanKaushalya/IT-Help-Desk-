<?php

declare(strict_types=1);

namespace App\Services\Administration;

use App\Models\AdmCategory;
use App\Models\AdmRequestType;
use App\Models\AdmSlaSetting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Read/update Administration SLA targets used to compute request due dates.
 *
 * Due date precedence (see AdmRequestService): type SLA setting, then category
 * SLA setting, then the defaults stored on the type/category.
 */
class AdmSlaService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function catalogue(): array
    {
        $settings = AdmSlaSetting::query()->where('is_active', true)->get();
        $byCategory = $settings->whereNull('adm_request_type_id')->keyBy('adm_category_id');
        $byType = $settings->whereNotNull('adm_request_type_id')->keyBy('adm_request_type_id');

        return AdmCategory::query()
            ->ordered()
            ->with(['requestTypes' => fn ($types) => $types->orderBy('sort_order')->orderBy('id')])
            ->get()
            ->map(fn (AdmCategory $category): array => [
                'id' => (int) $category->id,
                'slug' => $category->slug,
                'name' => $category->name,
                'is_active' => (bool) $category->is_active,
                'default_sla_hours' => $category->default_sla_hours,
                'target_hours' => $byCategory->get($category->id)?->target_hours ?? $category->default_sla_hours,
                'types' => $category->requestTypes->map(fn (AdmRequestType $type): array => [
                    'id' => (int) $type->id,
                    'slug' => $type->slug,
                    'name' => $type->name,
                    'sla_hours' => $type->sla_hours,
                    'target_hours' => $byType->get($type->id)?->target_hours ?? $type->sla_hours ?? $category->default_sla_hours,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  bool  $applyToTypes  also overwrite every request type of the category
     */
    public function updateCategory(AdmCategory $category, int $hours, bool $applyToTypes, User $actor): void
    {
        DB::transaction(function () use ($category, $hours, $applyToTypes): void {
            AdmSlaSetting::query()->updateOrCreate(
                ['adm_category_id' => $category->id, 'adm_request_type_id' => null],
                ['target_hours' => $hours, 'is_active' => true],
            );

            $category->forceFill(['default_sla_hours' => $hours])->save();

            if ($applyToTypes) {
                foreach ($category->requestTypes()->get() as $type) {
                    $this->writeType($type, $hours);
                }
            }
        });

        $this->audit->log(
            event: 'adm_sla.category_updated',
            subject: $category,
            newValues: ['target_hours' => $hours, 'applied_to_types' => $applyToTypes],
            description: $actor->username.' updated SLA for '.$category->name,
        );
    }

    public function updateType(AdmRequestType $type, int $hours, User $actor): void
    {
        DB::transaction(fn () => $this->writeType($type, $hours));

        $this->audit->log(
            event: 'adm_sla.type_updated',
            subject: $type,
            newValues: ['target_hours' => $hours],
            description: $actor->username.' updated SLA for '.$type->name,
        );
    }

    private function writeType(AdmRequestType $type, int $hours): void
    {
        AdmSlaSetting::query()->updateOrCreate(
            ['adm_category_id' => $type->adm_category_id, 'adm_request_type_id' => $type->id],
            ['target_hours' => $hours, 'is_active' => true],
        );

        $type->forceFill(['sla_hours' => $hours])->save();
    }
}
