<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Rest;

final readonly class Routes
{
    public function __construct(
        private HealthController $health,
        private IdentityController $identity,
        private ManifestController $manifest,
        private BridgeController $bridge,
    ) {
    }

    public function register(): void
    {
        register_rest_route('tropikal-connect/v1', '/health', [
            'methods' => 'GET',
            'callback' => $this->health,
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('tropikal-connect/v1', '/identity', [
            'methods' => 'GET',
            'callback' => $this->identity,
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('tropikal-connect/v1', '/manifest', [
            'methods' => 'GET',
            'callback' => $this->manifest,
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('tropikal-connect/v1', '/bridge', [
            'methods' => 'POST',
            'callback' => $this->bridge,
            'permission_callback' => '__return_true',
        ]);
    }
}
