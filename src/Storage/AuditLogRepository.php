<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Storage;

use TropikalAI\Connect\Domain\Security\SensitiveData;

final class AuditLogRepository
{
    public function install(): void
    {
        global $wpdb;

        $table = $this->table();
        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';

        $sql = "CREATE TABLE {$table} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(80) NOT NULL,
            resource_key varchar(191) NULL,
            operation varchar(120) NULL,
            target_type varchar(80) NULL,
            target_id varchar(191) NULL,
            actor_type varchar(80) NULL,
            actor_id varchar(191) NULL,
            request_id varchar(191) NULL,
            correlation_id varchar(191) NULL,
            approval_id varchar(191) NULL,
            status varchar(40) NOT NULL,
            error_code varchar(80) NULL,
            metadata_json longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY event_type (event_type),
            KEY resource_key (resource_key),
            KEY created_at (created_at)
        ) {$charset};";

        if (function_exists('dbDelta')) {
            dbDelta($sql);
        }
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function record(
        string $eventType,
        string $status,
        ?string $resourceKey = null,
        ?string $operation = null,
        ?string $targetId = null,
        ?string $correlationId = null,
        ?string $errorCode = null,
        array $metadata = [],
    ): void {
        global $wpdb;

        $wpdb->insert($this->table(), [
            'event_type' => $eventType,
            'resource_key' => $resourceKey,
            'operation' => $operation,
            'target_id' => $targetId,
            'actor_type' => 'tropikal',
            'correlation_id' => $correlationId,
            'approval_id' => is_scalar($metadata['approval_id'] ?? null) ? (string) $metadata['approval_id'] : null,
            'status' => $status,
            'error_code' => $errorCode,
            'metadata_json' => wp_json_encode(SensitiveData::redact($metadata)),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 10): array
    {
        global $wpdb;

        $limit = max(1, min(50, $limit));
        $rows = $wpdb->get_results("SELECT * FROM {$this->table()} ORDER BY id DESC LIMIT {$limit}", ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    private function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'tropikal_connect_audit_logs';
    }
}
