<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Services\TicketVerificationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Verification metadata for API clients.
 *
 * The plaintext code is included only on staff routes. Employees never
 * receive it. The hash is never serialised.
 *
 * @mixin \App\Models\Ticket
 */
class TicketVerificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $meta = app(TicketVerificationService::class)->metadata($this->resource);

        if ($meta === null) {
            return [];
        }

        $forStaff = $request->routeIs('api.v1.staff.*');

        return [
            'status' => $meta['status'],
            'requested_at' => $meta['requested_at'],
            'completed_at' => $meta['completed_at'],
            'is_blocked' => $meta['is_blocked'],
            'attempts' => $this->when($forStaff, $meta['attempts']),
            'expires_at' => $this->when($forStaff, $meta['expires_at']),
            'method' => $this->when($forStaff, $meta['method']),
            'code' => $this->when($forStaff, $meta['code']),
        ];
    }
}
