<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TropikalAI\Connect\Application\SignedRequestVerifier;
use TropikalAI\Connect\Domain\Security\SignedRequest;
use Tropikal\Connect\WordPress\Tests\Support\ArrayNonceStore;

/**
 * Byte-for-byte cross-language interop guard. The shared contract fixture pins
 * the canonical string + HMAC that the ops backend (Python) and every adapter
 * must reproduce. If WordPress signing canonicalization ever drifts, this fails.
 */
final class ContractSigningVectorTest extends TestCase
{
    /** @return array<string, mixed> */
    private function vector(): array
    {
        $vector = json_decode((string) file_get_contents(__DIR__ . '/../fixtures/signed-request.vector.json'), true);
        if (! is_array($vector)) {
            self::fail('vector fixture is not valid JSON');
        }

        return $vector;
    }

    public function testBodyHashMatchesTheContractVector(): void
    {
        $v = $this->vector();
        self::assertSame($v['body_hash'], SignedRequest::bodyHash((string) $v['body']));
    }

    public function testSignatureMatchesTheContractVectorByteForByte(): void
    {
        $v = $this->vector();
        $signature = SignedRequest::sign(
            (string) $v['secret'],
            (string) $v['installation_id'],
            (string) $v['method'],
            (string) $v['path'],
            $v['query'] === '' ? null : (string) $v['query'],
            (int) $v['timestamp'],
            (string) $v['nonce'],
            (string) $v['body_hash'],
        );

        self::assertSame($v['signature'], $signature, 'canonicalization has drifted from the contract');
    }

    public function testVerifierAcceptsTheContractSignedRequest(): void
    {
        $v = $this->vector();
        $headers = [
            SignedRequest::INSTALLATION_HEADER => $v['installation_id'],
            SignedRequest::TIMESTAMP_HEADER => (string) $v['timestamp'],
            SignedRequest::NONCE_HEADER => $v['nonce'],
            SignedRequest::BODY_HASH_HEADER => $v['body_hash'],
            SignedRequest::SIGNATURE_HEADER => $v['signature'],
        ];

        (new SignedRequestVerifier(new ArrayNonceStore()))->verify(
            (string) $v['secret'],
            (string) $v['installation_id'],
            (string) $v['method'],
            (string) $v['path'],
            $v['query'] === '' ? null : (string) $v['query'],
            (string) $v['body'],
            $headers,
            (int) $v['timestamp'],
        );

        $this->expectNotToPerformAssertions();
    }
}
