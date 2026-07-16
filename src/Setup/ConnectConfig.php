<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Setup;

/**
 * Connect lifecycle configuration. Defaults target the TROPIKAL production
 * endpoints; a site overrides them with constants or the
 * `tropikal_connect_wordpress_config` filter (e.g. to point at a local mock).
 */
final readonly class ConnectConfig
{
    /**
     * @param array<string, list<string>> $defaultGrants grants seeded on a successful connect
     */
    public function __construct(
        public string $siteUrl,
        public string $authorizationServerUrl,
        public string $controlPlaneUrl,
        public string $redirectUri,
        public string $scopes,
        public string $resource,
        public string $clientName,
        public array $defaultGrants = [],
        public string $authorizePath = '/oauth/authorize',
        public string $tokenPath = '/oauth/token',
        public string $registerClientPath = '/oauth/register',
        public string $registerInstallationPath = '/api/connect/installations',
        public ?string $configuredClientId = null,
        public int $timeoutSeconds = 15,
    ) {
    }

    public static function fromWordPress(): self
    {
        $const = static fn (string $name, string $default): string => (defined($name) && trim((string) constant($name)) !== '')
            ? trim((string) constant($name))
            : $default;

        $siteUrl = rtrim((string) home_url(), "/");
        $config = new self(
            siteUrl: $siteUrl,
            authorizationServerUrl: $const('TROPIKAL_CONNECT_AUTH_SERVER_URL', 'https://id.tropikal.ai'),
            controlPlaneUrl: $const('TROPIKAL_CONNECT_CONTROL_PLANE_URL', 'https://app.tropikal.ai'),
            redirectUri: admin_url('admin-post.php?action=tropikal_connect_callback'),
            scopes: $const('TROPIKAL_CONNECT_SCOPES', 'connect.install'),
            resource: $const('TROPIKAL_CONNECT_RESOURCE', $const('TROPIKAL_CONNECT_CONTROL_PLANE_URL', 'https://app.tropikal.ai')),
            clientName: 'TROPIKAL Connect for ' . (string) wp_parse_url($siteUrl, PHP_URL_HOST),
            defaultGrants: [],
        );

        $filtered = apply_filters('tropikal_connect_wordpress_config', $config);

        return $filtered instanceof self ? $filtered : $config;
    }
}
