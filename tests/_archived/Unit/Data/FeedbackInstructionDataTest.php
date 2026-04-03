<?php

use LBHurtado\Voucher\Data\FeedbackInstructionData;

it('parses a comma-delimited input with email, mobile, and webhook', function () {
    $input = 'feedback@acme.com, +639171234567, https://acme.com/webhook';

    $data = FeedbackInstructionData::fromText($input);

    expect($data->email)->toBe('feedback@acme.com')
        ->and($data->mobile)->toBe('+639171234567')
        ->and($data->webhook)->toBe('https://acme.com/webhook');
});

it('handles missing fields in the comma-delimited input', function () {
    $input = 'feedback@acme.com,https://acme.com/webhook';

    $data = FeedbackInstructionData::fromText($input);

    expect($data->email)->toBe('feedback@acme.com')
        ->and($data->mobile)->toBe(FeedbackInstructionData::defaultMobile()) // Mobile is missing
        ->and($data->webhook)->toBe('https://acme.com/webhook');
});

it('ignores extra, non-resolvable data gracefully', function () {
    $input = 'feedback@acme.com,garbage,+639171234567,https://acme.com/webhook';

    $data = FeedbackInstructionData::fromText($input);

    expect($data->email)->toBe('feedback@acme.com')
        ->and($data->mobile)->toBe('+639171234567')
        ->and($data->webhook)->toBe('https://acme.com/webhook');
});

it('resolves email, URL, and mobile regardless of order', function () {
    $input = '+639171234567,https://acme.com/webhook,feedback@acme.com';

    $data = FeedbackInstructionData::fromText($input);

    expect($data->email)->toBe('feedback@acme.com')
        ->and($data->mobile)->toBe('+639171234567')
        ->and($data->webhook)->toBe('https://acme.com/webhook');
});

it('returns default for all properties when input is empty', function () {
    $data = FeedbackInstructionData::fromText('');

    expect($data->email)->toBe(FeedbackInstructionData::defaultEmail())
        ->and($data->mobile)->toBe(FeedbackInstructionData::defaultMobile())
        ->and($data->webhook)->toBe(FeedbackInstructionData::defaultWebhook());
});

it('handles invalid input without assigning values', function () {
    $input = 'garbage,not_an_email,invalid_url';

    $data = FeedbackInstructionData::fromText($input);

    expect($data->email)->toBe(FeedbackInstructionData::defaultEmail())
        ->and($data->mobile)->toBe(FeedbackInstructionData::defaultMobile())
        ->and($data->webhook)->toBe(FeedbackInstructionData::defaultWebhook());
});

it('handles input with multiple valid values for the same type gracefully', function () {
    $input = 'feedback@acme.com,otheremail@domain.com,+639171234567,https://acme.com/webhook';

    $data = FeedbackInstructionData::fromText($input);

    expect($data->email)->toBe('feedback@acme.com') // Only the first valid email is used
        ->and($data->mobile)->toBe('+639171234567')
        ->and($data->webhook)->toBe('https://acme.com/webhook');
});
