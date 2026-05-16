<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Exception;

final class UnsupportedOperationException extends ConnectWordPressException
{
    public function __construct(string $message = 'Operation is not supported.')
    {
        parent::__construct($message, 'unsupported_operation', 400);
    }
}
