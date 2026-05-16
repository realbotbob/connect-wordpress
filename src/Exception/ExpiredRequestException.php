<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Exception;

final class ExpiredRequestException extends ConnectWordPressException
{
    public function __construct(string $message = 'Signed request timestamp is outside tolerance.')
    {
        parent::__construct($message, 'expired_request', 401);
    }
}
