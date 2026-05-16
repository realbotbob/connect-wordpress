<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Rest;

use Tropikal\Connect\WordPress\Storage\ConnectionRepository;

final readonly class HealthController
{
    public function __construct(private ConnectionRepository $connections)
    {
    }

    public function __invoke(\WP_REST_Request $request): \WP_REST_Response
    {
        unset($request);

        return new \WP_REST_Response([
            'status' => 'ok',
            'integration' => 'wordpress',
            'connected' => $this->connections->isConnected(),
            'plugin_version' => defined('TROPIKAL_CONNECT_WORDPRESS_VERSION') ? (string) constant('TROPIKAL_CONNECT_WORDPRESS_VERSION') : '0.1.0',
        ], 200);
    }
}
