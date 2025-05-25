<?php

namespace App\Exceptions;

use Illuminate\Http\Response;

class ExpenseException extends PaymentManagementException
{
    public static function notFound(): self
    {
        return new self(
            'Expense not found.',
            Response::HTTP_NOT_FOUND,
            'EXPENSE_NOT_FOUND'
        );
    }

    public static function accessDenied(): self
    {
        return new self(
            'You are not authorized to access this expense.',
            Response::HTTP_FORBIDDEN,
            'EXPENSE_ACCESS_DENIED'
        );
    }

    public static function updateNotAllowed(): self
    {
        return new self(
            'You are not authorized to update this expense.',
            Response::HTTP_FORBIDDEN,
            'EXPENSE_UPDATE_NOT_ALLOWED'
        );
    }

    public static function invalidPayer(): self
    {
        return new self(
            'The payer must be a member of the group.',
            Response::HTTP_BAD_REQUEST,
            'EXPENSE_INVALID_PAYER'
        );
    }

    public static function invalidParticipants(): self
    {
        return new self(
            'All participants must be members of the group.',
            Response::HTTP_BAD_REQUEST,
            'EXPENSE_INVALID_PARTICIPANTS'
        );
    }

    public static function shareNotFound(): self
    {
        return new self(
            'Expense share not found.',
            Response::HTTP_NOT_FOUND,
            'EXPENSE_SHARE_NOT_FOUND'
        );
    }

    public static function shareAlreadyPaid(): self
    {
        return new self(
            'This share is already fully paid.',
            Response::HTTP_BAD_REQUEST,
            'EXPENSE_SHARE_ALREADY_PAID'
        );
    }

    public static function paymentExceedsRemaining(float $remainingAmount): self
    {
        return new self(
            "Payment amount exceeds the remaining amount of {$remainingAmount}.",
            Response::HTTP_BAD_REQUEST,
            'EXPENSE_PAYMENT_EXCEEDS_REMAINING'
        );
    }

    public static function notBelongsToGroup(): self
    {
        return new self(
            'Expense does not belong to this group.',
            Response::HTTP_NOT_FOUND,
            'EXPENSE_NOT_BELONGS_TO_GROUP'
        );
    }

    public static function shareNotBelongsToExpense(): self
    {
        return new self(
            'Share does not belong to this expense.',
            Response::HTTP_NOT_FOUND,
            'EXPENSE_SHARE_NOT_BELONGS_TO_EXPENSE'
        );
    }
}
