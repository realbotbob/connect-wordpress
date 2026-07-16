<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Setup;

use Tropikal\Connect\WordPress\Exception\OAuthException;
use TropikalAI\Connect\Domain\OAuth\ClientRegistrationRequest;
use TropikalAI\Connect\Domain\OAuth\TokenRequest;
use TropikalAI\Connect\Domain\OAuth\TokenSet;

final readonly class WpAuthorizationServerGateway implements AuthorizationServerGateway
{
    public function __construct(private ConnectConfig $config, private WpHttp $http)
    {
    }

    public function registerClient(ClientRegistrationRequest $request): string
    {
        $body = $this->http->postJson($this->url($this->config->registerClientPath), $request->toArray());
        $clientId = trim((string) ($body['client_id'] ?? ''));
        if ($clientId === '') {
            throw new OAuthException('The authorization server returned an invalid client registration response.');
        }

        return $clientId;
    }

    public function exchangeCode(string $clientId, string $redirectUri, string $code, string $verifier, string $resource): TokenSet
    {
        $body = $this->http->postForm(
            $this->url($this->config->tokenPath),
            TokenRequest::authorizationCode($clientId, $redirectUri, $code, $verifier, $resource),
        );

        try {
            return TokenSet::fromArray($body);
        } catch (\InvalidArgumentException $e) {
            throw new OAuthException($e->getMessage());
        }
    }

    public function refreshAccessToken(string $clientId, string $refreshToken, string $resource): TokenSet
    {
        $body = $this->http->postForm(
            $this->url($this->config->tokenPath),
            TokenRequest::refreshToken($clientId, $refreshToken, $resource),
        );

        try {
            return TokenSet::fromArray($body);
        } catch (\InvalidArgumentException $e) {
            throw new OAuthException($e->getMessage());
        }
    }

    private function url(string $path): string
    {
        return rtrim($this->config->authorizationServerUrl, '/') . $path;
    }
}
