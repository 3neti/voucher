<?php

namespace LBHurtado\Voucher\Exceptions;

use Exception;

/**
 * Exception thrown when attempting to use an invalid settlement rail for a bank.
 *
 * This typically occurs when trying to disburse to an EMI (like GCash/PayMaya)
 * via PESONET, which is not supported. EMIs only accept INSTAPAY transfers.
 *
 * This exception prevents failed transactions and lost funds by validating
 * rail compatibility before sending requests to the payment gateway.
 */
class InvalidSettlementRailException extends Exception
{
    /**
     * Create a new exception instance for EMI + PESONET validation failure.
     *
     * @param  string  $bankName  Human-readable bank name (e.g., "GCash")
     * @param  string  $bankCode  SWIFT/BIC code (e.g., "GXCHPHM2XXX")
     * @param  string  $attemptedRail  The rail that was attempted (e.g., "PESONET")
     * @param  string  $allowedRails  Comma-separated allowed rails (e.g., "INSTAPAY")
     * @return static
     */
    public static function emiRequiresInstapay(
        string $bankName,
        string $bankCode,
        string $attemptedRail,
        string $allowedRails = 'INSTAPAY'
    ): self {
        return new self(
            sprintf(
                'Cannot disburse to EMI "%s" (%s) via %s. EMIs only support %s for real-time transfers.',
                $bankName,
                $bankCode,
                $attemptedRail,
                $allowedRails
            )
        );
    }

    /**
     * Create a new exception instance for amount limit violations.
     *
     * @param  float  $amount  The amount that was attempted
     * @param  string  $rail  The settlement rail
     * @param  float  $maxAmount  Maximum allowed amount
     * @return static
     */
    public static function amountExceedsLimit(float $amount, string $rail, float $maxAmount): self
    {
        return new self(
            sprintf(
                'Amount ₱%s exceeds the maximum limit of ₱%s for %s rail.',
                number_format($amount, 2),
                number_format($maxAmount, 2),
                $rail
            )
        );
    }

    /**
     * Create a new exception instance for unsupported rail.
     *
     * @param  string  $bankName  Bank name
     * @param  string  $rail  The unsupported rail
     * @param  array  $supportedRails  List of supported rails
     * @return static
     */
    public static function railNotSupported(string $bankName, string $rail, array $supportedRails): self
    {
        return new self(
            sprintf(
                'Bank "%s" does not support %s rail. Supported rails: %s',
                $bankName,
                $rail,
                implode(', ', $supportedRails)
            )
        );
    }
}
