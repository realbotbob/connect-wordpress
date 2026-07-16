<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Exception;

/** The connect (OAuth + registration) flow failed or was tampered with. */
final class OAuthException extends ConnectWordPressException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 'oauth_error', 400);
    }
}
