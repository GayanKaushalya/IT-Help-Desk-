<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Builds the single JSON envelope used by every API endpoint.
 *
 * Keeping the shape in one place means the frontend can rely on `success`,
 * `message`, `data` and `errors` always being present in the same form.
 */
final class ApiResponse
{
    public static function success(
        mixed $data = null,
        ?string $message = null,
        int $status = Response::HTTP_OK,
        array $meta = []
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    public static function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return self::success($data, $message, Response::HTTP_CREATED);
    }

    public static function noContent(): JsonResponse
    {
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public static function error(
        string $message,
        int $status = Response::HTTP_BAD_REQUEST,
        array $errors = [],
        ?string $code = null
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        if ($code !== null) {
            $payload['code'] = $code;
        }

        return response()->json($payload, $status);
    }

    /**
     * Flattens a paginator into `data` plus a predictable `meta` block, so the
     * frontend never has to deal with Laravel's nested pagination format.
     */
    public static function paginated(
        LengthAwarePaginator|ResourceCollection $paginator,
        ?string $message = null
    ): JsonResponse {
        $resource = $paginator instanceof ResourceCollection
            ? $paginator->resource
            : $paginator;

        return self::success(
            data: $paginator instanceof ResourceCollection
                ? $paginator->collection
                : $paginator->items(),
            message: $message,
            meta: [
                'current_page' => $resource->currentPage(),
                'per_page' => $resource->perPage(),
                'total' => $resource->total(),
                'last_page' => $resource->lastPage(),
                'from' => $resource->firstItem(),
                'to' => $resource->lastItem(),
            ],
        );
    }
}
