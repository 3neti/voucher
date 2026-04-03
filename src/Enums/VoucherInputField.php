<?php

namespace LBHurtado\Voucher\Enums;

enum VoucherInputField: string
{
    case EMAIL = 'email';
    case MOBILE = 'mobile';
    case REFERENCE_CODE = 'reference_code';
    case SIGNATURE = 'signature';
    case KYC = 'kyc';
    case NAME = 'name';
    case ADDRESS = 'address';
    case BIRTH_DATE = 'birth_date';
    case GROSS_MONTHLY_INCOME = 'gross_monthly_income';
    case LOCATION = 'location';
    case OTP = 'otp';
    case SELFIE = 'selfie';

    public static function valuesToCsv(): string
    {
        return implode(',', array_column(self::cases(), 'value'));
    }

    public static function options(): array
    {
        return array_map(
            fn (self $case) => [
                'label' => str(self::label($case))->headline()->toString(),
                'value' => $case->value,
            ],
            self::cases()
        );
    }

    protected static function label(self $case): string
    {
        return match ($case) {
            self::NAME => 'Full Name',
            self::EMAIL => 'Email Address',
            self::MOBILE => 'Mobile Number',
            self::REFERENCE_CODE => 'Reference Code',
            self::SIGNATURE => 'Signature',
            self::ADDRESS => 'Residential Address',
            self::BIRTH_DATE => 'Birth Date',
            self::GROSS_MONTHLY_INCOME => 'Gross Monthly Income',
            self::LOCATION => 'Location',
            self::OTP => 'OTP',
            self::SELFIE => 'Selfie Photo',
            self::KYC => 'Identity Verification (KYC)',
            // Add more custom labels here as needed
            default => $case->value,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
