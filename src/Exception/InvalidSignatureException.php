<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Exception;

final class InvalidSignatureException extends ConnectWordPressException
{
    public function __construct(string $message = 'Signed request could not be verified.')
    {
        parent::__construct($message, 'invalid_signature', 401);
    }
}
