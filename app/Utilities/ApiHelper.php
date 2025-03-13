<?php

namespace App\Utilities;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiHelper
{
    /**
     * Return a success JSON response.
     */
    public static function success(string $message = 'Success', array $data = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Return an error JSON response.
     */
    public static function error(string $message = 'Error', array $errors = [], int $status = 400): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    /**
     * Format a paginated response.
     */
    public static function paginatedResponse(LengthAwarePaginator $paginator, string $message = 'Data retrieved successfully'): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 200);
    }

    /**
     * Handle an API exception and return a formatted error response.
     */
    public static function handleException(\Exception $e, int $status = 500): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'An internal error occurred.',
            'error' => $e->getMessage(),
        ], $status);
    }
}
