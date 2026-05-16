<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Security;

use TropikalAI\Connect\Domain\Security\SensitiveData;

final class PublicPayloadGuard
{
    /**
     * @param array<mixed> $payload
     */
    public function assertSafe(array $payload): void
    {
        SensitiveData::assertPublicPayload($payload);
    }

    /**
     * @param array<mixed> $payload
     * @return array<mixed>
     */
    public function redact(array $payload): array
    {
        $redacted = SensitiveData::redact($payload);

        return is_array($redacted) ? $redacted : [];
    }

    public function isSensitiveKey(string $key): bool
    {
        return SensitiveData::isSensitiveKey($key);
    }
}
