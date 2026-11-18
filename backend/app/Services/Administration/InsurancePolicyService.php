<?php

declare(strict_types=1);

namespace App\Services\Administration;

use App\Models\InsurancePolicy;
use App\Models\InsurancePolicyHistory;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Insurance register: policies, their change history and documents.
 */
class InsurancePolicyService
{
    private const TRACKED = [
        'company_name', 'insurance_type', 'provider', 'policy_number', 'insured_asset',
        'vehicle_registration', 'start_date', 'expiry_date', 'premium_amount', 'sum_insured',
        'responsible_officer_id', 'renewal_status', 'status', 'notes',
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, InsurancePolicy>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = InsurancePolicy::query()->with('responsibleOfficer');

        if (filled($filters['search'] ?? null)) {
            $like = '%'.str_replace(['%', '_'], '', (string) $filters['search']).'%';
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('policy_number', 'like', $like)
                    ->orWhere('provider', 'like', $like)
                    ->orWhere('insured_asset', 'like', $like)
                    ->orWhere('vehicle_registration', 'like', $like)
                    ->orWhere('company_name', 'like', $like);
            });
        }

        foreach (['status', 'renewal_status', 'insurance_type', 'company_name'] as $column) {
            if (filled($filters[$column] ?? null)) {
                $query->where($column, (string) $filters[$column]);
            }
        }

        if (! empty($filters['responsible_officer_id'])) {
            $query->where('responsible_officer_id', (int) $filters['responsible_officer_id']);
        }

        $today = now()->startOfDay();

        if (! empty($filters['expired'])) {
            $query->whereNotNull('expiry_date')->where('expiry_date', '<', RegisterPagination::dayStart($today));
        } elseif (filled($filters['expiring_within'] ?? null)) {
            $query->whereBetween('expiry_date', [
                RegisterPagination::dayStart($today),
                RegisterPagination::dayEnd($today->copy()->addDays((int) $filters['expiring_within'])),
            ]);
        }

        return $query
            ->orderBy('expiry_date')
            ->orderByDesc('id')
            ->paginate(RegisterPagination::perPage($filters));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): InsurancePolicy
    {
        $policy = DB::transaction(function () use ($actor, $data): InsurancePolicy {
            $policy = InsurancePolicy::query()->create($data);
            $this->record($policy, $actor, 'created', 'Policy added to the register.');

            return $policy;
        });

        $this->audit->log(
            event: 'insurance_policy.created',
            subject: $policy,
            newValues: ['policy_number' => $policy->policy_number],
            description: $actor->username.' added insurance policy '.$policy->policy_number,
        );

        return $policy->load('responsibleOfficer');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(InsurancePolicy $policy, User $actor, array $data): InsurancePolicy
    {
        DB::transaction(function () use ($policy, $actor, $data): void {
            $policy->fill($data);
            $changes = [];

            foreach (self::TRACKED as $field) {
                if ($policy->isDirty($field)) {
                    $changes[$field] = [$policy->getOriginal($field), $policy->{$field}];
                }
            }

            if ($changes === []) {
                return;
            }

            $policy->save();

            if (isset($changes['renewal_status'])) {
                $this->record(
                    $policy,
                    $actor,
                    'renewal_status_changed',
                    'Renewal status changed from '.$this->label($changes['renewal_status'][0]).' to '.$this->label($changes['renewal_status'][1]).'.',
                );
            }

            $other = array_diff(array_keys($changes), ['renewal_status']);

            if ($other !== []) {
                $this->record($policy, $actor, 'updated', 'Updated: '.implode(', ', array_map(fn (string $f) => str_replace('_', ' ', $f), $other)).'.');
            }
        });

        $this->audit->log(
            event: 'insurance_policy.updated',
            subject: $policy,
            newValues: ['policy_number' => $policy->policy_number],
            description: $actor->username.' updated insurance policy '.$policy->policy_number,
        );

        return $policy->load('responsibleOfficer');
    }

    public function delete(InsurancePolicy $policy, User $actor): void
    {
        DB::transaction(function () use ($policy, $actor): void {
            $this->record($policy, $actor, 'deleted', 'Policy removed from the register.');
            $policy->delete();
        });

        $this->audit->log(
            event: 'insurance_policy.deleted',
            subject: $policy,
            description: $actor->username.' removed insurance policy '.$policy->policy_number,
        );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, InsurancePolicyHistory>
     */
    public function history(InsurancePolicy $policy)
    {
        return $policy->history()->with('actor')->orderByDesc('changed_at')->orderByDesc('id')->get();
    }

    public function storeDocument(InsurancePolicy $policy, User $actor, UploadedFile $file): InsurancePolicy
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');

        if (! in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'], true)) {
            throw ValidationException::withMessages(['document' => ['This file type is not allowed.']]);
        }

        $disk = 'local';
        $path = $file->storeAs('insurance-policies/'.$policy->id, Str::uuid()->toString().'.'.$extension, $disk);

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages(['document' => ['The document could not be stored. Please try again.']]);
        }

        $previous = $policy->hasDocument() ? [$policy->document_disk ?: $disk, $policy->document_path] : null;

        DB::transaction(function () use ($policy, $actor, $disk, $path, $file): void {
            $policy->forceFill([
                'document_disk' => $disk,
                'document_path' => $path,
                'document_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            ])->save();

            $this->record($policy, $actor, 'document_uploaded', 'Policy document uploaded.');
        });

        if ($previous !== null) {
            Storage::disk($previous[0])->delete($previous[1]);
        }

        return $policy->load('responsibleOfficer');
    }

    public function downloadDocument(InsurancePolicy $policy): StreamedResponse
    {
        if (! $policy->hasDocument()) {
            abort(404, 'This policy has no document.');
        }

        $disk = Storage::disk($policy->document_disk ?: 'local');

        if (! $disk->exists($policy->document_path)) {
            abort(404, 'The document is no longer available.');
        }

        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $policy->document_name) ?: 'policy-document';

        return $disk->download($policy->document_path, $safe, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function record(InsurancePolicy $policy, ?User $actor, string $event, ?string $notes = null): InsurancePolicyHistory
    {
        return InsurancePolicyHistory::query()->create([
            'policy_id' => $policy->id,
            'changed_by' => $actor?->id,
            'event' => $event,
            'notes' => $notes !== null ? mb_substr($notes, 0, 2000) : null,
            'changed_at' => now(),
        ]);
    }

    private function label(mixed $value): string
    {
        return $value === null || $value === '' ? 'none' : str_replace('_', ' ', (string) $value);
    }
}
