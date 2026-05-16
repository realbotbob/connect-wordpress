<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Security;

final class SecretStore
{
    public function encrypt(string $plain): string
    {
        $key = $this->key();
        if (function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
            $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
            $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plain, '', $nonce, $key);

            return base64_encode((string) wp_json_encode([
                'v' => 1,
                'alg' => 'xchacha20poly1305',
                'nonce' => base64_encode($nonce),
                'cipher' => base64_encode($cipher),
            ]));
        }

        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if (! is_string($cipher)) {
            throw new \RuntimeException('Unable to encrypt TROPIKAL Connect secret.');
        }

        return base64_encode((string) wp_json_encode([
            'v' => 1,
            'alg' => 'aes-256-gcm',
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'cipher' => base64_encode($cipher),
        ]));
    }

    public function decrypt(string $encoded): string
    {
        $payload = json_decode((string) base64_decode($encoded, true), true);
        if (! is_array($payload)) {
            throw new \RuntimeException('Unable to decrypt TROPIKAL Connect secret.');
        }

        $alg = (string) ($payload['alg'] ?? '');
        if ($alg === 'xchacha20poly1305' && function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_decrypt')) {
            $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                (string) base64_decode((string) ($payload['cipher'] ?? ''), true),
                '',
                (string) base64_decode((string) ($payload['nonce'] ?? ''), true),
                $this->key(),
            );

            if (is_string($plain)) {
                return $plain;
            }
        }

        if ($alg === 'aes-256-gcm') {
            $plain = openssl_decrypt(
                (string) base64_decode((string) ($payload['cipher'] ?? ''), true),
                'aes-256-gcm',
                $this->key(),
                OPENSSL_RAW_DATA,
                (string) base64_decode((string) ($payload['iv'] ?? ''), true),
                (string) base64_decode((string) ($payload['tag'] ?? ''), true),
            );

            if (is_string($plain)) {
                return $plain;
            }
        }

        throw new \RuntimeException('Unable to decrypt TROPIKAL Connect secret.');
    }

    public function available(): bool
    {
        try {
            $this->key();

            return function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt') || function_exists('openssl_encrypt');
        } catch (\RuntimeException) {
            return false;
        }
    }

    private function key(): string
    {
        $material = defined('TROPIKAL_CONNECT_ENCRYPTION_KEY') ? (string) constant('TROPIKAL_CONNECT_ENCRYPTION_KEY') : '';
        if (trim($material) === '') {
            $material = implode('|', array_filter([
                defined('AUTH_KEY') ? (string) constant('AUTH_KEY') : null,
                defined('SECURE_AUTH_KEY') ? (string) constant('SECURE_AUTH_KEY') : null,
                defined('LOGGED_IN_KEY') ? (string) constant('LOGGED_IN_KEY') : null,
                defined('NONCE_KEY') ? (string) constant('NONCE_KEY') : null,
            ]));
        }

        if (strlen($material) < 32) {
            throw new \RuntimeException('Configure TROPIKAL_CONNECT_ENCRYPTION_KEY or WordPress salts before connecting.');
        }

        return hash('sha256', $material, true);
    }
}
