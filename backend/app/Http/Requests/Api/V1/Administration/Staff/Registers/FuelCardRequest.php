<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Administration\Staff\Registers;

use App\Models\FuelCard;
use Closure;
use Illuminate\Validation\Rule;

/**
 * PINs and CVVs are never accepted (prohibited), and the card id must not be a
 * full card number: only an internal id plus a masked reference are stored.
 */
class FuelCardRequest extends RegisterRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'card_number' => [
                $this->presence(),
                'string',
                'max:60',
                Rule::unique('fuel_cards', 'card_number')->ignore($this->route('fuel_card')),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $digits = preg_replace('/[\s-]/', '', (string) $value) ?? '';

                    if (preg_match('/^\d{13,19}$/', $digits) === 1) {
                        $fail('Enter an internal card ID (for example FUEL-0012), not the full card number.');
                    }
                },
            ],
            'card_ref' => ['sometimes', 'nullable', 'string', 'max:40'],
            'pin' => ['prohibited'],
            'card_pin' => ['prohibited'],
            'cvv' => ['prohibited'],
            'provider' => ['sometimes', 'nullable', 'string', 'max:100'],
            'vehicle_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('vehicles', 'id')->whereNull('deleted_at'),
            ],
            'driver_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('drivers', 'id')->whereNull('deleted_at'),
            ],
            'assigned_user_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'monthly_limit' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999999'],
            'issued_at' => ['sometimes', 'nullable', 'date'],
            'expiry_date' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', Rule::in(FuelCard::STATUSES)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull([
            'company_name', 'card_ref', 'provider', 'vehicle_id', 'driver_id', 'assigned_user_id',
            'monthly_limit', 'issued_at', 'expiry_date', 'notes',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'card_number.unique' => 'A fuel card with this ID already exists.',
            'pin.prohibited' => 'Card PINs must never be entered into the help desk.',
            'card_pin.prohibited' => 'Card PINs must never be entered into the help desk.',
            'cvv.prohibited' => 'Card security codes must never be entered into the help desk.',
        ];
    }
}
