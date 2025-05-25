<?php

namespace App\Exceptions;

use Illuminate\Http\Response;

class GroupException extends PaymentManagementException
{
    public static function notFound(): self
    {
        return new self(
            'Group not found.',
            Response::HTTP_NOT_FOUND,
            'GROUP_NOT_FOUND'
        );
    }

    public static function accessDenied(): self
    {
        return new self(
            'You are not authorized to access this group.',
            Response::HTTP_FORBIDDEN,
            'GROUP_ACCESS_DENIED'
        );
    }

    public static function notMember(): self
    {
        return new self(
            'You are not a member of this group.',
            Response::HTTP_FORBIDDEN,
            'GROUP_NOT_MEMBER'
        );
    }

    public static function memberAlreadyExists(): self
    {
        return new self(
            'User is already a member of this group.',
            Response::HTTP_BAD_REQUEST,
            'GROUP_MEMBER_EXISTS'
        );
    }

    public static function invalidInvitation(): self
    {
        return new self(
            'Invalid invitation or invitation has already been processed.',
            Response::HTTP_BAD_REQUEST,
            'GROUP_INVALID_INVITATION'
        );
    }

    public static function deleteNotAllowed(): self
    {
        return new self(
            'You are not authorized to delete this group.',
            Response::HTTP_FORBIDDEN,
            'GROUP_DELETE_NOT_ALLOWED'
        );
    }
}
