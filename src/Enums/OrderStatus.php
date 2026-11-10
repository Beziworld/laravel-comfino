<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Enums;

/**
 * Status of a ComfinoPay loan application.
 *
 * ComfinoPay recommends deciding what to show the returning customer from
 * the final statuses only: ACCEPTED, WAITING_FOR_PAYMENT and PAID mean that
 * financing was granted, REJECTED and CANCELLED_BY_SHOP that it was not.
 * RESIGN and CANCELLED come from the dictionary of the official client and
 * are treated as unsuccessful final statuses.
 */
enum OrderStatus: string
{
    case Created = 'CREATED';
    case WaitingForFilling = 'WAITING_FOR_FILLING';
    case WaitingForConfirmation = 'WAITING_FOR_CONFIRMATION';
    case PreAccepted = 'PRE_ACCEPTED';
    case Accepted = 'ACCEPTED';
    case WaitingForPayment = 'WAITING_FOR_PAYMENT';
    case Paid = 'PAID';
    case Rejected = 'REJECTED';
    case Resign = 'RESIGN';
    case CancelledByShop = 'CANCELLED_BY_SHOP';
    case Cancelled = 'CANCELLED';

    /**
     * Financing was granted.
     */
    public function isSuccessful(): bool
    {
        return in_array($this, [self::Accepted, self::WaitingForPayment, self::Paid], true);
    }

    /**
     * Financing was not granted or the application was withdrawn.
     */
    public function isFailed(): bool
    {
        return in_array($this, [self::Rejected, self::Resign, self::CancelledByShop, self::Cancelled], true);
    }

    /**
     * The application is still in progress.
     */
    public function isPending(): bool
    {
        return ! $this->isSuccessful() && ! $this->isFailed();
    }

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Application created by the shop',
            self::WaitingForFilling => 'Waiting for the application form',
            self::WaitingForConfirmation => 'Waiting for confirmation by the lender',
            self::PreAccepted => 'Financing pre-approved',
            self::Accepted => 'Financing granted',
            self::WaitingForPayment => 'Payment in progress',
            self::Paid => 'Paid out to the shop',
            self::Rejected => 'Financing refused',
            self::Resign => 'Withdrawn by the customer',
            self::CancelledByShop => 'Cancelled by the shop',
            self::Cancelled => 'Cancelled',
        };
    }
}
