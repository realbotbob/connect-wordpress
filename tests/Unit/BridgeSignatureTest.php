<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tropikal\Connect\WordPress\Security\NonceStore;
use Tropikal\Connect\WordPress\Security\SecretStore;
use Tropikal\Connect\WordPress\Security\SignatureVerifier;
use Tropikal\Connect\WordPress\Storage\ConnectionRepository;
use Tropikal\Connect\WordPress\Storage\OptionsRepository;
use TropikalAI\Connect\Application\SignedRequestVerifier;
use TropikalAI\Connect\Domain\Security\SignedRequest;

final class BridgeSignatureTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['wp_options'] = [];
        $GLOBALS['wpdb'] = new \FakeWpdb();
    }

    public function testValidSignedRequestIsAcceptedAndReplayRejected(): void
    {
        $options = new OptionsRepository();
        (new ConnectionRepository($options))->saveRegistration(
            ['installation_id' => 'inst_123', 'site_id' => 'site_123', 'key_id' => 'key_123'],
            (new SecretStore())->encrypt('server-secret'),
            '1',
        );
        $body = '{"operation":"wordpress.resource.search","resource_key":"post","payload":{}}';
        $headers = SignedRequest::headers('server-secret', 'inst_123', 'POST', '/wp-json/tropikal-connect/v1/bridge', [], $body);
        $verifier = new SignatureVerifier(new ConnectionRepository($options), new SecretStore(), new SignedRequestVerifier(new NonceStore()));

        $verifier->verify('POST', '/wp-json/tropikal-connect/v1/bridge', [], $body, $headers);
        $this->expectException(\Tropikal\Connect\WordPress\Exception\InvalidSignatureException::class);
        $verifier->verify('POST', '/wp-json/tropikal-connect/v1/bridge', [], $body, $headers);
    }
}
