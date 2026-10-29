<?php

namespace App\Services\Saims;

use RuntimeException;

/**
 * Raised by SaimsClient. The message is always the friendly text of the
 * failure, so it is safe to show or log.
 */
class SaimsException extends RuntimeException
{
    public function __construct(
        public readonly SaimsFailure $failure,
        public readonly ?int $status = null,
    ) {
        parent::__construct($failure->message());
    }

    /**
     * What the pages show: a stable code and the friendly message.
     *
     * @return array{code: string, message: string}
     */
    public function toArray(): array
    {
        return ['code' => $this->failure->value, 'message' => $this->failure->message()];
    }
}
