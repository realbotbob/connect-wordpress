<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Security;

use TropikalAI\Connect\Application\Ports\NonceStore as CoreNonceStore;

final class NonceStore implements CoreNonceStore
{
    public function install(): void
    {
        global $wpdb;

        $table = $this->table();
        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';

        $sql = "CREATE TABLE {$table} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            installation_id varchar(191) NOT NULL,
            nonce_hash char(64) NOT NULL,
            created_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY nonce_installation (installation_id, nonce_hash),
            KEY expires_at (expires_at)
        ) {$charset};";

        if (function_exists('dbDelta')) {
            dbDelta($sql);
        }
    }

    public function claim(string $installationId, string $nonce, int $ttlSeconds): bool
    {
        global $wpdb;

        $nonce = trim($nonce);
        if ($installationId === '' || $nonce === '') {
            return false;
        }

        $result = $wpdb->insert($this->table(), [
            'installation_id' => $installationId,
            'nonce_hash' => hash('sha256', $nonce),
            'created_at' => gmdate('Y-m-d H:i:s'),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + max(1, $ttlSeconds)),
        ]);

        return $result !== false;
    }

    public function cleanup(): void
    {
        global $wpdb;

        $wpdb->query($wpdb->prepare("DELETE FROM {$this->table()} WHERE expires_at < %s", gmdate('Y-m-d H:i:s')));
    }

    private function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'tropikal_connect_nonces';
    }
}
