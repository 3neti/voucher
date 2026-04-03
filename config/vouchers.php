<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Code Generation
    |--------------------------------------------------------------------------
    */

    /*
     * List of characters allowed in code generation.
     * To avoid confusion the characters 1 (one), 0 (zero), I and O are removed.
     */
    'characters' => '23456789ABCDEFGHJKLMNPQRSTUVWXYZ',

    /*
     * Code generation mask.
     * Only asterisks will be replaced, so you can add or remove as many asterisks you want.
     *
     * Ex: ***-**-***
     */
    'mask' => '****-****',

    /*
     * Code prefix.
     * If defined all codes will start with this string.
     *
     * Ex. FOO-1234-5678
     */
    'prefix' => null,

    /*
     * Code suffix.
     * If defined all codes will end with this string.
     *
     * Ex. 1234-5678-BAR
     */
    'suffix' => null,

    /*
     * Separator for code prefix and suffix.
     */
    'separator' => '-',

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */

    'models' => [
        'entity' => FrittenKeeZ\Vouchers\Models\VoucherEntity::class,
        'redeemer' => FrittenKeeZ\Vouchers\Models\Redeemer::class,
        'voucher' => LBHurtado\Voucher\Models\Voucher::class,
    ],

    'tables' => [
        'entities' => 'voucher_entity',
        'redeemers' => 'redeemers',
        'vouchers' => 'vouchers',
    ],

    /*
    |--------------------------------------------------------------------------
    | Settlement Rules
    |--------------------------------------------------------------------------
    | Default rules for settlement vouchers (PAYABLE/SETTLEMENT types)
    */

    'settlement' => [
        'default_rules' => [
            'min_payment' => 1.00,              // Minimum payment amount (PHP)
            'max_payment' => null,              // Maximum payment amount (null = no limit)
            'allow_overpayment' => false,       // Allow payments exceeding target_amount
            'auto_close_on_full_payment' => true, // Auto-close when fully paid
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mobile Verification (Redemption)
    |--------------------------------------------------------------------------
    | Driver-based mobile verification during voucher redemption.
    | Same pattern as config/database.php connections.
    | Voucher instructions can override the driver name and enforcement.
    | Parameters always come from config (no credentials in voucher data).
    */

    'mobile_verification' => [
        'default' => env('REDEMPTION_MOBILE_VERIFICATION_DRIVER', 'basic'),
        'enforcement' => env('REDEMPTION_MOBILE_VERIFICATION_ENFORCEMENT', 'strict'),
        'show_in_generate' => env('REDEMPTION_MOBILE_VERIFICATION_SHOW_IN_GENERATE', false),

        'drivers' => [
            'basic' => [
                'class' => LBHurtado\Voucher\MobileVerification\Drivers\BasicDriver::class,
            ],

            'countries' => [
                'class' => LBHurtado\Voucher\MobileVerification\Drivers\CountriesDriver::class,
                'countries' => array_filter(explode(',', env('REDEMPTION_MOBILE_VERIFICATION_COUNTRIES', 'PH'))),
            ],

            'white_list' => [
                'class' => LBHurtado\Voucher\MobileVerification\Drivers\WhiteListDriver::class,
                'mobiles' => array_filter(explode(',', env('REDEMPTION_MOBILE_VERIFICATION_MOBILES', ''))),
                'file' => env('REDEMPTION_MOBILE_VERIFICATION_FILE'),
                'column' => env('REDEMPTION_MOBILE_VERIFICATION_COLUMN'),
            ],

            'external_api' => [
                'class' => LBHurtado\Voucher\MobileVerification\Drivers\ExternalApiDriver::class,
                'url' => env('REDEMPTION_MOBILE_VERIFICATION_API_URL'),
                'method' => env('REDEMPTION_MOBILE_VERIFICATION_API_METHOD', 'POST'),
                'mobile_param' => env('REDEMPTION_MOBILE_VERIFICATION_API_MOBILE_PARAM', 'mobile'),
                'timeout' => (int) env('REDEMPTION_MOBILE_VERIFICATION_API_TIMEOUT', 5),
                'headers' => [
                    'Authorization' => 'Bearer '.env('REDEMPTION_MOBILE_VERIFICATION_API_TOKEN', ''),
                    'Accept' => 'application/json',
                ],
                'extra_params' => [],
                'response_field' => env('REDEMPTION_MOBILE_VERIFICATION_API_RESPONSE_FIELD', 'valid'),
            ],

            'external_db' => [
                'class' => LBHurtado\Voucher\MobileVerification\Drivers\ExternalDbDriver::class,
                'connection' => env('REDEMPTION_MOBILE_VERIFICATION_DB_CONNECTION'),
                'table' => env('REDEMPTION_MOBILE_VERIFICATION_DB_TABLE'),
                'column' => env('REDEMPTION_MOBILE_VERIFICATION_DB_COLUMN'),
            ],
        ],
    ],
];
