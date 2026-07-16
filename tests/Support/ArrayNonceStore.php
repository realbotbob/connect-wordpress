<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Tests\Support;

use TropikalAI\Connect\Application\Ports\NonceStore;

final class ArrayNonceStore implements NonceStore
{
    /** @var array<string, true> */
    private array $seen = [];

    public function claim(string $installationId, string $nonce, int $ttlSeconds): bool
    {
        $key = $installationId . ':' . $nonce;
        if (isset($this->seen[$key])) {
            return false;
        }
        $this->seen[$key] = true;

        return true;
    }
}
