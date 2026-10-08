<?php

namespace App\Services\QuanthumLicense\Exceptions;

use RuntimeException;
use Throwable;

class LicenseActivationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
