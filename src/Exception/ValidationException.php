<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Exception;

final class ValidationException extends ConnectWordPressException
{
    public function __construct(string $message = 'Request payload is invalid.')
    {
        parent::__construct($message, 'validation_error', 422);
    }
}
