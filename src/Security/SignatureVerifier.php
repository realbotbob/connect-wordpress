<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Security;

use Tropikal\Connect\WordPress\Exception\InvalidSignatureException;
use Tropikal\Connect\WordPress\Storage\ConnectionRepository;
use TropikalAI\Connect\Application\SignedRequestVerifier;
use TropikalAI\Connect\Exceptions\ConnectException;

final readonly class SignatureVerifier
{
    public function __construct(
        private ConnectionRepository $connections,
        private SecretStore $secrets,
        private SignedRequestVerifier $verifier,
    ) {
    }

    /**
     * @param array<string, mixed> $headers
     */
    public function verify(string $method, string $path, array|string|null $query, string $body, array $headers): void
    {
        $installationId = $this->connections->installationId();
        $encryptedSecret = $this->connections->encryptedSecret();

        if ($installationId === null || $encryptedSecret === null || $this->connections->isRevoked()) {
            throw new InvalidSignatureException('Connect installation is not available.');
        }

        try {
            $this->verifier->verify(
                secret: $this->secrets->decrypt($encryptedSecret),
                expectedInstallationId: $installationId,
                method: $method,
                path: $path,
                query: $query,
                body: $body,
                headers: $headers,
            );
        } catch (ConnectException $exception) {
            throw new InvalidSignatureException($exception->getMessage());
        }
    }
}
