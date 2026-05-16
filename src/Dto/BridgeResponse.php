<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Dto;

final readonly class BridgeResponse
{
    /**
     * @param array<string, mixed> $payload
     */
    private function __construct(
        public array $payload,
        public int $statusCode,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function ok(array $payload): self
    {
        return new self(['status' => 'ok', 'data' => $payload], 200);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function created(array $payload): self
    {
        return new self(['status' => 'created', 'data' => $payload], 201);
    }

    public static function deleted(string|int $id): self
    {
        return new self(['status' => 'ok', 'data' => ['id' => (string) $id, 'deleted' => true]], 200);
    }

    public static function error(string $code, string $message, int $statusCode): self
    {
        return new self([
            'status' => 'error',
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $statusCode);
    }
}
