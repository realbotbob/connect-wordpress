<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Setup;

final readonly class WpControlPlaneGateway implements ControlPlaneGateway
{
    public function __construct(private ConnectConfig $config, private WpHttp $http)
    {
    }

    public function registerInstallation(array $payload, string $accessToken): array
    {
        return $this->http->postJson(
            rtrim($this->config->controlPlaneUrl, '/') . $this->config->registerInstallationPath,
            $payload,
            ['Authorization' => 'Bearer ' . $accessToken],
        );
    }
}
