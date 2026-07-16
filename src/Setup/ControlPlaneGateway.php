<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Setup;

/** Outbound calls to the TROPIKAL control plane. */
interface ControlPlaneGateway
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function registerInstallation(array $payload, string $accessToken): array;
}
