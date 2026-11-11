<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Company locations used by registration, tickets and the user directory.
 */
class AdminBranchService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{search: string|null, status: string|null, per_page: int|null}  $filters
     * @return LengthAwarePaginator<int, Branch>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = $filters['per_page']
            ?? (int) config('helpdesk.pagination.default_per_page');
        $perPage = max(1, min($perPage, (int) config('helpdesk.pagination.max_per_page')));

        $query = Branch::query();

        if (filled($filters['search'] ?? null)) {
            $term = str_replace(['%', '_'], '', (string) $filters['search']);

            if ($term !== '') {
                $like = '%'.$term.'%';
                $query->where(function (Builder $inner) use ($like): void {
                    $inner->where('name', 'like', $like)
                        ->orWhere('code', 'like', $like)
                        ->orWhere('city', 'like', $like)
                        ->orWhere('address', 'like', $like);
                });
            }
        }

        if (($filters['status'] ?? null) === 'active') {
            $query->where('is_active', true);
        } elseif (($filters['status'] ?? null) === 'inactive') {
            $query->where('is_active', false);
        }

        return $query->ordered()->orderBy('id')->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Branch
    {
        $branch = Branch::query()->create([
            'name' => $data['name'],
            'code' => $this->normaliseCode((string) $data['code']),
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        $this->audit->log(
            event: 'branch.created',
            subject: $branch,
            newValues: ['code' => $branch->code, 'name' => $branch->name],
            description: $actor->username.' created branch '.$branch->code,
        );

        return $branch;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Branch $branch, array $data): Branch
    {
        if (isset($data['code'])) {
            $data['code'] = $this->normaliseCode((string) $data['code']);
        }

        $branch->fill($data);
        $original = $branch->getOriginal();
        $dirty = $branch->getDirty();
        $branch->save();

        if ($dirty !== []) {
            $this->audit->log(
                event: 'branch.updated',
                subject: $branch,
                oldValues: array_intersect_key($original, $dirty),
                newValues: $dirty,
                description: $actor->username.' updated branch '.$branch->code,
            );
        }

        return $branch->fresh() ?? $branch;
    }

    public function activate(User $actor, Branch $branch): Branch
    {
        $branch->forceFill(['is_active' => true])->save();

        $this->audit->log(
            event: 'branch.activated',
            subject: $branch,
            newValues: ['is_active' => true],
            description: $actor->username.' activated branch '.$branch->code,
        );

        return $branch->fresh() ?? $branch;
    }

    public function deactivate(User $actor, Branch $branch): Branch
    {
        $branch->forceFill(['is_active' => false])->save();

        $this->audit->log(
            event: 'branch.deactivated',
            subject: $branch,
            newValues: ['is_active' => false],
            description: $actor->username.' deactivated branch '.$branch->code,
        );

        return $branch->fresh() ?? $branch;
    }

    private function normaliseCode(string $code): string
    {
        return mb_strtoupper(trim($code));
    }
}
