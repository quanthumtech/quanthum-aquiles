<?php

namespace App\Services\QuanthumSso\Exceptions;

use App\Services\QuanthumSso\QuanthumSsoFailure;
use RuntimeException;
use Throwable;

class QuanthumSsoException extends RuntimeException
{
    public function __construct(
        public readonly QuanthumSsoFailure $failure,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : $failure->value, 0, $previous);
    }
}
