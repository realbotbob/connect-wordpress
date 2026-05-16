<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Exception;

final class ReplayDetectedException extends ConnectWordPressException
{
    public function __construct(string $message = 'Signed request nonce has already been used.')
    {
        parent::__construct($message, 'replay_detected', 401);
    }
}
