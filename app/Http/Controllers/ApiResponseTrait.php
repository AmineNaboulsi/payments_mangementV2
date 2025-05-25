<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

trait ApiResponseTrait
{
    
    protected function successResponse($data = null, string $message = 'Success', int $status = Response::HTTP_OK): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $status);
    }

    protected function errorResponse(
        string $message = 'An error occurred',
        int $status = Response::HTTP_INTERNAL_SERVER_ERROR,
        $errors = null,
        string $errorCode = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        if ($errorCode !== null) {
            $response['error_code'] = $errorCode;
        }

        return response()->json($response, $status);
    }

    
    protected function validationErrorResponse(ValidationException $exception): JsonResponse
    {
        return $this->errorResponse(
            'Validation failed',
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $exception->errors(),
            'VALIDATION_ERROR'
        );
    }

   
    protected function notFoundResponse(string $resource = 'Resource'): JsonResponse
    {
        return $this->errorResponse(
            "{$resource} not found",
            Response::HTTP_NOT_FOUND,
            null,
            'NOT_FOUND'
        );
    }

    
    protected function forbiddenResponse(string $message = 'Access denied'): JsonResponse
    {
        return $this->errorResponse(
            $message,
            Response::HTTP_FORBIDDEN,
            null,
            'ACCESS_DENIED'
        );
    }

   
    protected function unauthenticatedResponse(string $message = 'Authentication required'): JsonResponse
    {
        return $this->errorResponse(
            $message,
            Response::HTTP_UNAUTHORIZED,
            null,
            'UNAUTHENTICATED'
        );
    }

    
    protected function badRequestResponse(string $message = 'Bad request', $errors = null): JsonResponse
    {
        return $this->errorResponse(
            $message,
            Response::HTTP_BAD_REQUEST,
            $errors,
            'BAD_REQUEST'
        );
    }

    
    protected function handleRequest(callable $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (ValidationException $e) {
            Log::warning('Validation error', [
                'errors' => $e->errors(),
                'user_id' => auth()->id(),
                'route' => request()->route()?->getName(),
                'method' => request()->method(),
                'url' => request()->url(),
            ]);
            
            return $this->validationErrorResponse($e);
        } catch (HttpException $e) {
            Log::warning('HTTP exception', [
                'status' => $e->getStatusCode(),
                'message' => $e->getMessage(),
                'user_id' => auth()->id(),
                'route' => request()->route()?->getName(),
                'method' => request()->method(),
                'url' => request()->url(),
            ]);

            return $this->errorResponse(
                $e->getMessage() ?: 'HTTP error occurred',
                $e->getStatusCode(),
                null,
                'HTTP_ERROR'
            );
        } catch (Throwable $e) {
            Log::error('Unexpected error in controller', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'route' => request()->route()?->getName(),
                'method' => request()->method(),
                'url' => request()->url(),
                'request_data' => request()->except(['password', 'password_confirmation']),
            ]);

            return $this->errorResponse(
                'An unexpected error occurred. Please try again later.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                null,
                'INTERNAL_ERROR'
            );
        }
    }

    /**
     * Log and handle database constraint violations
     */
    protected function handleDatabaseError(Throwable $e, string $operation = 'database operation'): JsonResponse
    {
        Log::error("Database error during {$operation}", [
            'message' => $e->getMessage(),
            'user_id' => auth()->id(),
            'trace' => $e->getTraceAsString(),
        ]);

        // Check for common database constraint violations
        if (str_contains($e->getMessage(), 'Duplicate entry')) {
            return $this->badRequestResponse('This record already exists');
        }

        if (str_contains($e->getMessage(), 'foreign key constraint')) {
            return $this->badRequestResponse('Cannot perform this action due to related data');
        }

        if (str_contains($e->getMessage(), 'Data too long')) {
            return $this->badRequestResponse('Some data exceeds the maximum allowed length');
        }

        return $this->errorResponse(
            "Failed to perform {$operation}. Please try again later.",
            Response::HTTP_INTERNAL_SERVER_ERROR,
            null,
            'DATABASE_ERROR'
        );
    }
}
