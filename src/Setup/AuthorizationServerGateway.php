<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Setup;

use TropikalAI\Connect\Domain\OAuth\ClientRegistrationRequest;
use TropikalAI\Connect\Domain\OAuth\TokenSet;

/** Outbound calls to the TROPIKAL authorization server (OAuth 2.1 + PKCE). */
interface AuthorizationServerGateway
{
    public function registerClient(ClientRegistrationRequest $request): string;

    public function exchangeCode(string $clientId, string $redirectUri, string $code, string $verifier, string $resource): TokenSet;

    public function refreshAccessToken(string $clientId, string $refreshToken, string $resource): TokenSet;
}
