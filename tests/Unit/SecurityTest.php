<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tropikal\Connect\WordPress\Security\NonceStore;
use Tropikal\Connect\WordPress\Security\PublicPayloadGuard;
use Tropikal\Connect\WordPress\Security\SecretStore;

final class SecurityTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['wpdb'] = new \FakeWpdb();
    }

    public function testSecretsRoundTripEncrypted(): void
    {
        $store = new SecretStore();
        $encrypted = $store->encrypt('server-secret');

        self::assertNotSame('server-secret', $encrypted);
        self::assertSame('server-secret', $store->decrypt($encrypted));
    }

    public function testNonceStoreRejectsReplay(): void
    {
        $store = new NonceStore();

        self::assertTrue($store->claim('inst_123', 'nonce-1', 300));
        self::assertFalse($store->claim('inst_123', 'nonce-1', 300));
        self::assertTrue($store->claim('inst_123', 'nonce-2', 300));
    }

    public function testPublicPayloadGuardRejectsSecretsRecursively(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new PublicPayloadGuard())->assertSafe([
            'resources' => [
                ['label' => 'Posts', 'refresh_token' => 'nope'],
            ],
        ]);
    }
}
