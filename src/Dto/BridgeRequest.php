<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Dto;

final readonly class BridgeRequest
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $operation,
        public string $resourceKey,
        public array $payload = [],
        public ?string $correlationId = null,
    ) {
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function fromArray(array $body): self
    {
        $payload = is_array($body['payload'] ?? null) ? $body['payload'] : [];
        $resourceKey = trim((string) ($body['resource_key'] ?? $payload['type'] ?? ''));

        return new self(
            operation: trim((string) ($body['operation'] ?? '')),
            resourceKey: $resourceKey,
            payload: $payload,
            correlationId: isset($body['correlation_id']) ? trim((string) $body['correlation_id']) : null,
        );
    }
}
