<?php
namespace MSM;

use RuntimeException;

final class ApiConnectorException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorType = 'api_error',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
