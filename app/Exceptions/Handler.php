<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        // Force JSON responses for API routes or when JSON is expected
        if ($request->is('api/*') || $request->expectsJson() || $request->wantsJson()) {
            return $this->renderJsonResponse($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * Render a JSON response for the given exception.
     */
    protected function renderJsonResponse(Request $request, Throwable $e)
    {
        $status = $this->getStatusCode($e);
        $message = $this->getMessage($e);
        
        $response = [
            'success' => false,
            'message' => $message,
            'status_code' => $status,
        ];

        // Add error details in debug mode
        if (config('app.debug')) {
            $response['debug'] = [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'message' => $e->getMessage(),
            ];
        }

        // Add validation errors for validation exceptions
        if ($e instanceof ValidationException) {
            $response['errors'] = $e->errors();
            $response['error_code'] = 'VALIDATION_ERROR';
            $response['message'] = 'The given data was invalid.';
        }

        // Add error codes for different exception types
        if ($e instanceof AuthenticationException) {
            $response['error_code'] = 'AUTHENTICATION_ERROR';
        } elseif ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            $response['error_code'] = 'NOT_FOUND';
        } elseif ($e instanceof AccessDeniedHttpException) {
            $response['error_code'] = 'ACCESS_DENIED';
        } elseif ($e instanceof MethodNotAllowedHttpException) {
            $response['error_code'] = 'METHOD_NOT_ALLOWED';
        } elseif ($e instanceof QueryException) {
            $response['error_code'] = 'DATABASE_ERROR';
        } elseif ($e instanceof HttpException) {
            $response['error_code'] = 'HTTP_ERROR';
        } else {
            $response['error_code'] = 'INTERNAL_ERROR';
        }

        return response()->json($response, $status);
    }

    /**
     * Get the appropriate status code for the exception.
     */
    protected function getStatusCode(Throwable $e): int
    {
        if ($e instanceof HttpException) {
            return $e->getStatusCode();
        }

        if ($e instanceof AuthenticationException) {
            return Response::HTTP_UNAUTHORIZED;
        }

        if ($e instanceof ValidationException) {
            return Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return Response::HTTP_NOT_FOUND;
        }

        if ($e instanceof AccessDeniedHttpException) {
            return Response::HTTP_FORBIDDEN;
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            return Response::HTTP_METHOD_NOT_ALLOWED;
        }

        if ($e instanceof QueryException) {
            return Response::HTTP_INTERNAL_SERVER_ERROR;
        }

        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    /**
     * Get the appropriate message for the exception.
     */
    protected function getMessage(Throwable $e): string
    {
        if ($e instanceof HttpException) {
            return $e->getMessage() ?: $this->getDefaultMessage($e->getStatusCode());
        }

        if ($e instanceof AuthenticationException) {
            return 'Authentication required. Please log in to access this resource.';
        }

        if ($e instanceof ValidationException) {
            return 'The given data was invalid.';
        }

        if ($e instanceof ModelNotFoundException) {
            return 'The requested resource was not found.';
        }

        if ($e instanceof NotFoundHttpException) {
            return 'The requested resource was not found.';
        }

        if ($e instanceof AccessDeniedHttpException) {
            return 'Access denied. You do not have permission to access this resource.';
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            return 'The requested method is not allowed for this resource.';
        }

        if ($e instanceof QueryException) {
            if (config('app.debug')) {
                return 'Database error: ' . $e->getMessage();
            }
            return 'A database error occurred. Please try again later.';
        }

        if (config('app.debug')) {
            return $e->getMessage();
        }

        return 'An unexpected error occurred. Please try again later.';
    }

    /**
     * Get default message for HTTP status codes.
     */
    protected function getDefaultMessage(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Bad request. The request could not be understood.',
            401 => 'Authentication required.',
            403 => 'Access denied.',
            404 => 'Resource not found.',
            405 => 'Method not allowed.',
            409 => 'Conflict. The request could not be completed due to a conflict.',
            422 => 'The given data was invalid.',
            429 => 'Too many requests. Please slow down.',
            500 => 'Internal server error.',
            502 => 'Bad gateway.',
            503 => 'Service unavailable.',
            default => 'An error occurred.',
        };
    }

    /**
     * Convert an authentication exception into a response.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required. Please log in to access this resource.',
                'status_code' => Response::HTTP_UNAUTHORIZED,
            ], Response::HTTP_UNAUTHORIZED);
        }

        return redirect()->guest(route('login'));
    }
}
