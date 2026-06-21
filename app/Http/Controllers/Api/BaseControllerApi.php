<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class BaseControllerApi extends Controller
{
    protected function responseApiProfile(
        bool $success,
        string $message,
        mixed $data = null,
        int $statusCode = Response::HTTP_OK
    ): JsonResponse {
        return response()->json([
            'success' => $success,
            'status_code' => $statusCode,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }
}