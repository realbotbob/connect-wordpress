<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Setup;

use Tropikal\Connect\WordPress\Discovery\WordPressSchemaMapper;
use Tropikal\Connect\WordPress\Security\SecretStore;
use Tropikal\Connect\WordPress\Storage\AuditLogRepository;
use Tropikal\Connect\WordPress\Storage\ConnectionRepository;
use Tropikal\Connect\WordPress\WordPress\SiteIdentityProvider;

final readonly class RegistrationService
{
    public function __construct(
        private SiteIdentityProvider $identity,
        private WordPressSchemaMapper $schema,
        private ControlPlaneClient $client,
        private ConnectionRepository $connections,
        private SecretStore $secrets,
        private AuditLogRepository $audit,
    ) {
    }

    public function register(string $adminId): void
    {
        $secret = bin2hex(random_bytes(32));
        $keyId = 'wp_' . bin2hex(random_bytes(8));
        $identity = $this->identity->identity();
        $payload = [
            'integration' => 'wordpress',
            'plugin_version' => $identity->pluginVersion,
            'site' => $identity->toArray(),
            'admin' => ['id' => $adminId],
            'bridge_url' => rest_url('tropikal-connect/v1/bridge'),
            'manifest_url' => rest_url('tropikal-connect/v1/manifest'),
            'public_identifier' => $keyId,
            'manifest' => $this->schema->manifest($identity)->toArray(),
        ];

        $response = $this->client->register($payload);
        $response['key_id'] ??= $keyId;
        $response['installation_id'] ??= $response['connection_id'] ?? '';
        if (trim((string) $response['installation_id']) === '') {
            throw new \RuntimeException('TROPIKAL Connect registration did not return an installation id.');
        }

        $this->connections->saveRegistration($response, $this->secrets->encrypt((string) ($response['signing_secret'] ?? $secret)), $adminId);
        $this->audit->record('connect', 'success', metadata: ['installation_id' => $response['installation_id']]);
    }

    public function syncManifest(): void
    {
        $manifest = $this->schema->manifest($this->identity->identity())->toArray();
        $this->client->syncManifest($manifest);
        $this->connections->markSynced();
        $this->audit->record('manifest_sync', 'success');
    }

    public function rotateSecret(): void
    {
        $keyId = 'wp_' . bin2hex(random_bytes(8));
        $this->connections->rotate($keyId, $this->secrets->encrypt(bin2hex(random_bytes(32))));
        $this->audit->record('rotate_secret', 'success');
    }

    public function revoke(): void
    {
        $this->connections->revoke();
        $this->audit->record('disconnect', 'success');
    }
}
