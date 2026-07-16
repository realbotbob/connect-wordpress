<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Setup;

use TropikalAI\Connect\Domain\OAuth\OAuthState;

/**
 * State persisted between the Connect click and the OAuth callback: the client
 * id, the HASHED state (never the plain value), the PKCE verifier, the expiry,
 * and who started it. Stored encrypted at rest by PendingAuthorizationStore.
 */
final readonly class PendingAuthorization
{
    public function __construct(
        public string $clientId,
        public string $stateHash,
        public string $codeVerifier,
        public int $expiresAt,
        public string $adminId,
    ) {
    }

    public function matches(string $plainState): bool
    {
        return OAuthState::valid($plainState, $this->stateHash, new \DateTimeImmutable('@' . $this->expiresAt));
    }
}
