<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Tests\Support;

use Tropikal\Connect\WordPress\Setup\AuthorizationServerGateway;
use TropikalAI\Connect\Domain\OAuth\ClientRegistrationRequest;
use TropikalAI\Connect\Domain\OAuth\TokenSet;
use TropikalAI\Connect\Domain\Security\Base64Url;

/** Fake authorization server that enforces PKCE (S256) so the verifier round-trip is proven. */
final class FakeAuthorizationServer implements AuthorizationServerGateway
{
    public string $issuedClientId = 'wp_client_fake';

    public string $issuedAccessToken = 'wp_at_fake';

    public string $refreshedWith = '';

    /** @var array<string, string> */
    private array $codes = [];

    public function registerClient(ClientRegistrationRequest $request): string
    {
        return $this->issuedClientId;
    }

    public function issueCode(string $challenge): string
    {
        $code = 'code_' . bin2hex(random_bytes(6));
        $this->codes[$code] = $challenge;

        return $code;
    }

    public function exchangeCode(string $clientId, string $redirectUri, string $code, string $verifier, string $resource): TokenSet
    {
        $challenge = $this->codes[$code] ?? null;
        if ($challenge === null || ! hash_equals($challenge, Base64Url::encode(hash('sha256', $verifier, true)))) {
            throw new \RuntimeException('PKCE verification failed.');
        }

        return TokenSet::fromArray(['access_token' => $this->issuedAccessToken, 'refresh_token' => 'wp_rt_fake']);
    }

    public function refreshAccessToken(string $clientId, string $refreshToken, string $resource): TokenSet
    {
        $this->refreshedWith = $refreshToken;

        return TokenSet::fromArray(['access_token' => $this->issuedAccessToken, 'refresh_token' => 'wp_rt_fake_2']);
    }
}
