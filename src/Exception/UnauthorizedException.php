<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Exception;

final class UnauthorizedException extends ConnectWordPressException
{
    public function __construct(string $message = 'Unauthorized.')
    {
        parent::__construct($message, 'unauthorized', 403);
    }
}
