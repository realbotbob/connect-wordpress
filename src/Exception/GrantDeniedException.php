<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Exception;

final class GrantDeniedException extends ConnectWordPressException
{
    public function __construct(string $message = 'Connected Data grant is not enabled.')
    {
        parent::__construct($message, 'grant_denied', 403);
    }
}
