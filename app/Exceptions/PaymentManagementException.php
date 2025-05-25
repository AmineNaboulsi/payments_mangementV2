<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Response;

class PaymentManagementException extends Exception
{
    protected $statusCode;
    protected $errorCode;

    public function __construct(
        string $message = 'An error occurred',
        int $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR,
        string $errorCode = 'GENERAL_ERROR',
        \Throwable $previous = null
    ) {
        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode;
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function render($request)
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => $this->getErrorCode(),
            'status_code' => $this->getStatusCode(),
        ], $this->getStatusCode());
    }
}
