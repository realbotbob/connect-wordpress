<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Exception;

class ConnectWordPressException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'wordpress_error',
        public readonly int $statusCode = 500,
    ) {
        parent::__construct($message);
    }
}
