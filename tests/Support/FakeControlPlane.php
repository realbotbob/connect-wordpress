<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Tests\Support;

use Tropikal\Connect\WordPress\Setup\ControlPlaneGateway;

/** Captures the registration call and returns a configurable response. */
final class FakeControlPlane implements ControlPlaneGateway
{
    public string $issuedSigningKey = 'bfs_wp_server_signing_key';

    public string $issuedInstallationId = 'inst_oauth_1';

    /** @var array<string, mixed>|null */
    public ?array $response = null;

    /** @var array<string, mixed> */
    public array $seenPayload = [];

    public string $seenAccessToken = '';

    public function registerInstallation(array $payload, string $accessToken): array
    {
        $this->seenPayload = $payload;
        $this->seenAccessToken = $accessToken;

        return $this->response ?? [
            'installation_id' => $this->issuedInstallationId,
            'site_id' => 'site_oauth_1',
            'key_id' => 'key_oauth_1',
            'account_label' => 'Fake Workspace',
            'server_signing_key' => $this->issuedSigningKey,
        ];
    }
}
