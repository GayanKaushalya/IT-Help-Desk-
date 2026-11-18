<?php

declare(strict_types=1);

namespace App\Services\Administration;

use App\Models\FuelCard;
use App\Models\FuelCardHistory;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Fuel card register. Only an internal card id and a masked reference are
 * stored; the full card number and PIN are never persisted.
 */
class FuelCardService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Last four characters of a card reference, e.g. "**** **** **** 1234".
     */
    public static function mask(?string $reference): ?string
    {
        $clean = preg_replace('/[^A-Za-z0-9]/', '', (string) $reference) ?? '';

        if ($clean === '') {
            return null;
        }

        return '**** **** **** '.substr($clean, -4);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, FuelCard>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = FuelCard::query()->with(['vehicle', 'driver', 'assignedUser']);

        if (filled($filters['search'] ?? null)) {
            $like = RegisterPagination::like((string) $filters['search']);
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('card_number', 'like', $like)
                    ->orWhere('provider', 'like', $like)
                    ->orWhere('company_name', 'like', $like)
                    ->orWhere('masked_card_ref', 'like', $like)
                    ->orWhereHas('vehicle', fn (Builder $vehicle) => $vehicle->where('registration_number', 'like', $like));
            });
        }

        foreach (['status', 'company_name', 'provider'] as $column) {
            if (filled($filters[$column] ?? null)) {
                $query->where($column, (string) $filters[$column]);
            }
        }

        if (! empty($filters['vehicle_id'])) {
            $query->where('vehicle_id', (int) $filters['vehicle_id']);
        }

        return $query->orderBy('card_number')->paginate(RegisterPagination::perPage($filters));
    }

    /**
     * @param  array<string, mixed>  $data  may contain a write-only `card_ref`
     */
    public function create(User $actor, array $data): FuelCard
    {
        $data = $this->prepare($data);

        $card = DB::transaction(function () use ($actor, $data): FuelCard {
            $card = FuelCard::query()->create($data);
            $this->record($card, $actor, 'issued', 'Card added to the register.');

            return $card;
        });

        $this->audit->log(
            event: 'fuel_card.created',
            subject: $card,
            newValues: ['card_number' => $card->card_number],
            description: $actor->username.' added fuel card '.$card->card_number,
        );

        return $card->load(['vehicle', 'driver', 'assignedUser']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(FuelCard $card, User $actor, array $data): FuelCard
    {
        $data = $this->prepare($data);

        DB::transaction(function () use ($card, $actor, $data): void {
            $card->fill($data);

            $assignmentChanged = $card->isDirty(['vehicle_id', 'driver_id', 'assigned_user_id']);
            $statusChange = $card->isDirty('status') ? [$card->getOriginal('status'), $card->status] : null;
            $dirty = $card->isDirty();

            if (! $dirty) {
                return;
            }

            $card->save();

            if ($statusChange !== null) {
                $this->record($card, $actor, 'status_changed', 'Status changed from '.$statusChange[0].' to '.$statusChange[1].'.');
            }

            if ($assignmentChanged) {
                $this->record($card, $actor, 'reassigned', 'Card assignment updated.');
            }

            if ($statusChange === null && ! $assignmentChanged) {
                $this->record($card, $actor, 'updated', 'Card details updated.');
            }
        });

        $this->audit->log(
            event: 'fuel_card.updated',
            subject: $card,
            newValues: ['card_number' => $card->card_number, 'status' => $card->status],
            description: $actor->username.' updated fuel card '.$card->card_number,
        );

        return $card->load(['vehicle', 'driver', 'assignedUser']);
    }

    public function delete(FuelCard $card, User $actor): void
    {
        DB::transaction(function () use ($card, $actor): void {
            $this->record($card, $actor, 'removed', 'Card removed from the register.');
            $card->delete();
        });

        $this->audit->log(
            event: 'fuel_card.deleted',
            subject: $card,
            description: $actor->username.' removed fuel card '.$card->card_number,
        );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, FuelCardHistory>
     */
    public function history(FuelCard $card)
    {
        return $card->history()->with('actor')->orderByDesc('changed_at')->orderByDesc('id')->get();
    }

    public function record(FuelCard $card, ?User $actor, string $event, ?string $notes = null): FuelCardHistory
    {
        return FuelCardHistory::query()->create([
            'fuel_card_id' => $card->id,
            'changed_by' => $actor?->id,
            'event' => $event,
            'notes' => $notes !== null ? mb_substr($notes, 0, 2000) : null,
            'changed_at' => now(),
        ]);
    }

    /**
     * Turns the write-only `card_ref` into a masked reference and drops it,
     * along with anything PIN-like, so it can never reach the database.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepare(array $data): array
    {
        if (array_key_exists('card_ref', $data)) {
            $masked = self::mask(is_string($data['card_ref']) ? $data['card_ref'] : null);

            if ($masked !== null) {
                $data['masked_card_ref'] = $masked;
            }
        }

        unset($data['card_ref'], $data['pin'], $data['card_pin'], $data['cvv']);

        return $data;
    }
}
