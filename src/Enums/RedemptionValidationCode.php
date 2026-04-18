<?php

namespace LBHurtado\Voucher\Enums;

enum RedemptionValidationCode: string
{
    case MISSING = 'missing';
    case OUTSIDE_RADIUS = 'outside_radius';
    case INVALID = 'invalid';
    case OTP_NOT_VERIFIED = 'otp_not_verified';
    case FACE_MATCH_NOT_VERIFIED = 'face_match_not_verified';
    case FACE_MATCH_CONFIDENCE_TOO_LOW = 'face_match_confidence_too_low';
    case OUTSIDE_TIME_WINDOW = 'outside_time_window';
    case TIME_LIMIT_EXCEEDED = 'time_limit_exceeded';
}