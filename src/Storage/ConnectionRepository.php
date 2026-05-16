<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Storage;

final readonly class ConnectionRepository
{
    public function __construct(private OptionsRepository $options)
    {
    }

    public function isConnected(): bool
    {
        return $this->installationId() !== null && ! $this->isRevoked();
    }

    public function installationId(): ?string
    {
        return $this->options->string('connection_id') ?? $this->options->string('installation_id');
    }

    public function siteId(): ?string
    {
        return $this->options->string('site_id');
    }

    public function keyId(): ?string
    {
        return $this->options->string('key_id');
    }

    public function encryptedSecret(): ?string
    {
        return $this->options->string('encrypted_secret');
    }

    public function isRevoked(): bool
    {
        return $this->options->bool('revoked');
    }

    public function accountLabel(): ?string
    {
        return $this->options->string('account_label');
    }

    public function lastSyncAt(): ?string
    {
        return $this->options->string('last_sync_at');
    }

    public function lastBridgeCallAt(): ?string
    {
        return $this->options->string('last_bridge_call_at');
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function saveRegistration(array $payload, string $encryptedSecret, string $createdBy): void
    {
        $now = gmdate(DATE_ATOM);
        $this->options->set('connection_id', (string) ($payload['installation_id'] ?? $payload['connection_id'] ?? ''));
        $this->options->set('site_id', (string) ($payload['site_id'] ?? ''));
        $this->options->set('key_id', (string) ($payload['key_id'] ?? ''));
        $this->options->set('encrypted_secret', $encryptedSecret);
        $this->options->set('revoked', false);
        $this->options->set('account_label', (string) ($payload['account_label'] ?? ''));
        $this->options->set('created_by', $createdBy);
        $this->options->set('created_at', $this->options->string('created_at') ?? $now);
        $this->options->set('updated_at', $now);
    }

    public function markSynced(): void
    {
        $now = gmdate(DATE_ATOM);
        $this->options->set('last_sync_at', $now);
        $this->options->set('updated_at', $now);
    }

    public function markBridgeCall(): void
    {
        $this->options->set('last_bridge_call_at', gmdate(DATE_ATOM));
    }

    public function revoke(): void
    {
        $this->options->set('revoked', true);
        $this->options->set('updated_at', gmdate(DATE_ATOM));
    }

    public function rotate(string $keyId, string $encryptedSecret): void
    {
        $this->options->set('key_id', $keyId);
        $this->options->set('encrypted_secret', $encryptedSecret);
        $this->options->set('updated_at', gmdate(DATE_ATOM));
    }

    /**
     * @return array<string, mixed>
     */
    public function publicStatus(): array
    {
        return [
            'connected' => $this->isConnected(),
            'installation_id' => $this->installationId(),
            'site_id' => $this->siteId(),
            'account_label' => $this->accountLabel(),
            'revoked' => $this->isRevoked(),
            'last_sync_at' => $this->lastSyncAt(),
            'last_bridge_call_at' => $this->lastBridgeCallAt(),
        ];
    }
}
