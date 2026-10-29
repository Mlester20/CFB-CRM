<?php

namespace App\Services\Saims;

/**
 * Why a SAIMS call did not work, with a message that is safe to show to users:
 * it never contains the token, the URL or anything SAIMS sent back.
 */
enum SaimsFailure: string
{
    case Disabled = 'disabled';
    case NotConfigured = 'not_configured';
    case Unreachable = 'unreachable';
    case Unauthorized = 'token_rejected';
    case Forbidden = 'forbidden';
    case NotFound = 'not_found';
    case RateLimited = 'rate_limited';
    case Failed = 'failed';

    public function message(): string
    {
        return match ($this) {
            self::Disabled => 'The SAIMS connection is turned off.',
            self::NotConfigured => 'SAIMS is not connected yet. Ask an administrator to set it up.',
            self::Unreachable => 'SAIMS could not be reached. Please try again in a moment.',
            self::Unauthorized => 'SAIMS rejected the connection token. Ask an administrator to check it.',
            self::Forbidden => 'The SAIMS connection is not allowed to read this data.',
            self::NotFound => 'That record was not found in SAIMS.',
            self::RateLimited => 'SAIMS is receiving too many requests right now. Please wait a minute and try again.',
            self::Failed => 'SAIMS returned an unexpected answer. Please try again later.',
        };
    }
}
