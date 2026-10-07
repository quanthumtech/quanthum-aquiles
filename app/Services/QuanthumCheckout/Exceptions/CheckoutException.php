<?php

namespace App\Services\QuanthumCheckout\Exceptions;

use RuntimeException;
use Throwable;

class CheckoutException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        public readonly ?int $httpStatus = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
