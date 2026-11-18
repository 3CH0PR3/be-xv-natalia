<?php

namespace App\Traits\Api;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

trait ApiResponser
{
    /**
     * Return error response.
     */
    protected function sendError(
        ?string $message = null,
        array $errors = [],
        int $code = 404
    ): JsonResponse {
        $code = (int) $code;
        if ($code < 100 || $code > 599) {
            $code = 500;
        }

        $response = [
            'success' => false,
            'code' => $code,
        ];

        if (!is_null($message)) {
            $response['message'] = $message;
        }

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Return success response.
     */
    protected function sendResponse(
        mixed $data = [],
        ?string $message = null,
        int $code = 200
    ): JsonResponse {
        if ($code < 100 || $code > 599) {
            $code = 200;
        }

        $response = [
            'success' => true,
            'code' => $code,
            'data' => $data,
        ];

        if (!is_null($message)) {
            $response['message'] = $message;
        }

        return response()->json($response, $code);
    }

    /**
     * Return paginated response.
     */
    protected function sendPaginatedResponse(
        ?object $paginated,
        ?string $message = null,
        array $data = []
    ): JsonResponse {
        $response = [
            'success' => true,
            'code' => 200,
            'data' => !empty($data) ? $data : $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
            'links' => [
                'first' => $paginated->url(1),
                'last' => $paginated->url($paginated->lastPage()),
                'prev' => $paginated->previousPageUrl(),
                'next' => $paginated->nextPageUrl(),
            ],
        ];

        if ($message) {
            $response['message'] = $message;
        }

        return response()->json($response, 200);
    }

    /**
     * Return cursor paginated response.
     */
    protected function sendCursorPaginatedResponse(
        ?object $paginated,
        ?string $message = null,
        array $data = []
    ): JsonResponse {
        $response = [
            'success' => true,
            'code' => 200,
            'data' => !empty($data) ? $data : $paginated->items(),
            'meta' => [
                'per_page' => $paginated->perPage(),
                'next_cursor' => $paginated->nextCursor()?->encode(),
                'prev_cursor' => $paginated->previousCursor()?->encode(),
                'has_more' => $paginated->hasMorePages(),
            ],
            'links' => [
                'next' => $paginated->nextPageUrl(),
                'prev' => $paginated->previousPageUrl(),
            ],
        ];

        if ($message) {
            $response['message'] = $message;
        }

        return response()->json($response, 200);
    }
}
