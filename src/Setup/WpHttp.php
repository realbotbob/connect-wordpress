<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Setup;

use Tropikal\Connect\WordPress\Exception\OAuthException;

/** Thin wrapper around wp_remote_post that returns a decoded JSON array. */
final readonly class WpHttp
{
    public function __construct(private int $timeoutSeconds = 15)
    {
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function postJson(string $url, array $body, array $headers = []): array
    {
        return $this->request($url, (string) wp_json_encode($body), ['Content-Type' => 'application/json', ...$headers]);
    }

    /**
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    public function postForm(string $url, array $form): array
    {
        return $this->request($url, http_build_query($form), ['Content-Type' => 'application/x-www-form-urlencoded']);
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function request(string $url, string $body, array $headers): array
    {
        $this->assertSecureEndpoint($url);

        $response = wp_remote_post($url, [
            'headers' => ['Accept' => 'application/json', ...$headers],
            'body' => $body,
            'timeout' => $this->timeoutSeconds,
        ]);

        if (is_wp_error($response)) {
            throw new OAuthException((string) $response->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            throw new OAuthException("The TROPIKAL server rejected the request with HTTP {$status}.");
        }

        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
        if (! is_array($decoded)) {
            throw new OAuthException('The TROPIKAL server returned an invalid response.');
        }

        return $decoded;
    }

    /**
     * Refuse to send the authorization code, bearer token, or refresh token over
     * cleartext. https is required; http is tolerated only for loopback hosts so
     * local development against a mock server still works.
     */
    private function assertSecureEndpoint(string $url): void
    {
        $scheme = strtolower((string) wp_parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.localhost');

        if ($scheme !== 'https' && ! ($scheme === 'http' && $isLocal)) {
            throw new OAuthException('TROPIKAL endpoints must use https (http is allowed only for localhost).');
        }
    }
}
