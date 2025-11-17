<?php

namespace ElSchneider\StatamicSimpleAddress\Exceptions;

class ProviderApiException extends \Exception
{
    private int $statusCode;

    public function __construct(string $message, int $statusCode)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
