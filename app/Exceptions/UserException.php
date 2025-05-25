<?php

namespace App\Exceptions;

use Illuminate\Http\Response;

class UserException extends PaymentManagementException
{
    public static function notFound(): self
    {
        return new self(
            'User not found.',
            Response::HTTP_NOT_FOUND,
            'USER_NOT_FOUND'
        );
    }

    public static function invalidCredentials(): self
    {
        return new self(
            'Invalid credentials provided.',
            Response::HTTP_UNAUTHORIZED,
            'USER_INVALID_CREDENTIALS'
        );
    }

    public static function emailAlreadyExists(): self
    {
        return new self(
            'User with this email already exists.',
            Response::HTTP_CONFLICT,
            'USER_EMAIL_EXISTS'
        );
    }

    public static function accountLocked(): self
    {
        return new self(
            'Account is locked. Please contact support.',
            Response::HTTP_FORBIDDEN,
            'USER_ACCOUNT_LOCKED'
        );
    }

    public static function registrationFailed(): self
    {
        return new self(
            'User registration failed. Please try again.',
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'USER_REGISTRATION_FAILED'
        );
    }
}
