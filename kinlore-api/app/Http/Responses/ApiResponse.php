<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiResponse
{
    private const NO_DATA = '__API_RESPONSE_NO_DATA__';

    public static function success(
        mixed $data = self::NO_DATA,
        ?string $message = null,
        int $status = Response::HTTP_OK,
        array $meta = [],
        array $extra = []
    ): JsonResponse {
        $payload = array_merge(['success' => true], $extra);

        if ($message !== null) {
            $payload['message'] = $message;
        }

        if ($data !== self::NO_DATA) {
            $payload['data'] = $data;
        }

        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    public static function error(
        string $message,
        int $status = Response::HTTP_BAD_REQUEST,
        array $errors = [],
        array $extra = []
    ): JsonResponse {
        $payload = array_merge([
            'success' => false,
            'message' => $message,
        ], $extra);

        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    public static function validation(
        array $errors,
        ?string $message = 'Validation failed',
        int $status = Response::HTTP_UNPROCESSABLE_ENTITY,
        array $extra = []
    ): JsonResponse {
        return self::error($message ?? 'Validation failed', $status, $errors, $extra);
    }
}
