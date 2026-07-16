<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Setup;

use Tropikal\Connect\WordPress\Security\SecretStore;
use Tropikal\Connect\WordPress\Storage\OptionsRepository;

/**
 * Stores the transient PendingAuthorization encrypted at rest (the PKCE
 * verifier is a secret). One pending authorization at a time.
 */
final readonly class PendingAuthorizationStore
{
    private const OPTION = 'pending_authorization';

    public function __construct(
        private OptionsRepository $options,
        private SecretStore $secrets,
    ) {
    }

    public function save(PendingAuthorization $pending): void
    {
        $this->options->set(self::OPTION, $this->secrets->encrypt((string) wp_json_encode([
            'client_id' => $pending->clientId,
            'state_hash' => $pending->stateHash,
            'code_verifier' => $pending->codeVerifier,
            'expires_at' => $pending->expiresAt,
            'admin_id' => $pending->adminId,
        ])));
    }

    public function load(): ?PendingAuthorization
    {
        $encoded = $this->options->string(self::OPTION);
        if ($encoded === null) {
            return null;
        }

        try {
            $data = json_decode($this->secrets->decrypt($encoded), true);
        } catch (\Throwable) {
            return null;
        }
        if (! is_array($data)) {
            return null;
        }

        return new PendingAuthorization(
            (string) ($data['client_id'] ?? ''),
            (string) ($data['state_hash'] ?? ''),
            (string) ($data['code_verifier'] ?? ''),
            (int) ($data['expires_at'] ?? 0),
            (string) ($data['admin_id'] ?? ''),
        );
    }

    public function clear(): void
    {
        $this->options->delete(self::OPTION);
    }
}
