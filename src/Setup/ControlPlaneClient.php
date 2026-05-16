<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Setup;

use TropikalAI\Connect\Domain\Security\SensitiveData;

final class ControlPlaneClient
{
    public function register(array $payload): array
    {
        SensitiveData::assertPublicPayload($payload);
        $url = apply_filters('tropikal_connect_wordpress_registration_url', '');
        if (! is_string($url) || trim($url) === '') {
            throw new \RuntimeException('TROPIKAL Connect registration URL is not configured.');
        }

        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($payload),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            throw new \RuntimeException((string) $response->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($status < 200 || $status >= 300 || ! is_array($body)) {
            throw new \RuntimeException('TROPIKAL Connect registration failed.');
        }

        return $body;
    }

    public function syncManifest(array $payload): void
    {
        SensitiveData::assertPublicPayload($payload);
        $url = apply_filters('tropikal_connect_wordpress_manifest_sync_url', '');
        if (! is_string($url) || trim($url) === '') {
            return;
        }

        wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($payload),
            'timeout' => 15,
        ]);
    }
}
