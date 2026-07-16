<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Setup;

use Tropikal\Connect\WordPress\Discovery\WordPressSchemaMapper;
use Tropikal\Connect\WordPress\Exception\OAuthException;
use Tropikal\Connect\WordPress\Security\SecretStore;
use Tropikal\Connect\WordPress\Storage\AuditLogRepository;
use Tropikal\Connect\WordPress\Storage\ConnectionRepository;
use Tropikal\Connect\WordPress\Storage\GrantRepository;
use Tropikal\Connect\WordPress\WordPress\SiteIdentityProvider;
use TropikalAI\Connect\Domain\OAuth\AuthorizationRequest;
use TropikalAI\Connect\Domain\OAuth\ClientRegistrationRequest;
use TropikalAI\Connect\Domain\OAuth\OAuthState;
use TropikalAI\Connect\Domain\OAuth\PkcePair;

/**
 * The one-click connect lifecycle for WordPress, mirroring the Filament and n2n
 * adapters.
 *
 *   begin()    — ensure an OAuth client (dynamic registration if none is
 *                configured), generate PKCE + hashed state, persist the pending
 *                authorization, and return the authorization URL to redirect to.
 *   complete() — validate the callback (state, expiry, redirect URI), exchange
 *                the code for tokens, register the installation on the control
 *                plane (Bearer access token + capability manifest), and store the
 *                control-plane-issued server signing key.
 *
 * Fails closed: the connection is stored only when the control plane returns a
 * server signing key. There is NO locally-generated secret fallback.
 */
final readonly class ConnectFlow
{
    public function __construct(
        private ConnectConfig $config,
        private PendingAuthorizationStore $pending,
        private AuthorizationServerGateway $authServer,
        private ControlPlaneGateway $controlPlane,
        private ConnectionRepository $connections,
        private SecretStore $secrets,
        private GrantRepository $grants,
        private WordPressSchemaMapper $schema,
        private SiteIdentityProvider $identity,
        private AuditLogRepository $audit,
    ) {
    }

    public function begin(string $adminId): string
    {
        $clientId = $this->config->configuredClientId
            ?? $this->authServer->registerClient(new ClientRegistrationRequest(
                $this->config->clientName,
                [$this->config->redirectUri],
                $this->config->scopes,
                $this->config->resource,
                $this->config->siteUrl,
                '',
            ));

        $state = OAuthState::generate();
        $pkce = PkcePair::generate();

        $this->pending->save(new PendingAuthorization(
            $clientId,
            $state->hash,
            $pkce->verifier,
            $state->expiresAt->getTimestamp(),
            $adminId,
        ));

        return (new AuthorizationRequest(
            rtrim($this->config->authorizationServerUrl, '/') . $this->config->authorizePath,
            $clientId,
            $this->config->redirectUri,
            $this->config->scopes,
            $this->config->resource,
            $state->plain,
            $pkce,
        ))->url();
    }

    public function complete(string $state, string $code, string $callbackUrl): void
    {
        if ($state === '' || $code === '') {
            throw new OAuthException('The OAuth callback is missing its state or code.');
        }
        if (! $this->matchesRedirect($callbackUrl)) {
            throw new OAuthException('The OAuth callback URL does not match the configured redirect URI.');
        }

        $pending = $this->pending->load();
        if ($pending === null || ! $pending->matches($state)) {
            throw new OAuthException('The OAuth state is invalid or has expired.');
        }

        $tokens = $this->authServer->exchangeCode(
            $pending->clientId,
            $this->config->redirectUri,
            $code,
            $pending->codeVerifier,
            $this->config->resource,
        );

        if ($this->config->defaultGrants !== []) {
            $this->grants->replace($this->config->defaultGrants);
        }

        $body = $this->controlPlane->registerInstallation($this->registrationPayload(), $tokens->accessToken);

        $signingKey = trim((string) ($body['server_signing_key'] ?? $body['signing_secret'] ?? ''));
        $installationId = trim((string) ($body['installation_id'] ?? $body['connection_id'] ?? ''));
        if ($signingKey === '' || $installationId === '') {
            $this->audit->record('connect', 'error', metadata: ['reason' => 'missing_server_credentials']);
            throw new OAuthException('The control plane response did not include server credentials.');
        }

        $body['installation_id'] = $installationId;
        $this->connections->saveRegistration($body, $this->secrets->encrypt($signingKey), $pending->adminId);
        $this->connections->saveOAuth($pending->clientId, $this->secrets->encrypt($tokens->refreshToken));
        $this->pending->clear();
        $this->audit->record('connect', 'success', metadata: ['installation_id' => $installationId]);
    }

    /**
     * Re-push the current capability manifest (e.g. after a grant change), using
     * the stored refresh token to obtain a fresh access token. Requires a live
     * connection; there is no unauthenticated sync path.
     */
    public function sync(): void
    {
        $clientId = $this->connections->oauthClientId();
        $encryptedRefresh = $this->connections->encryptedRefreshToken();
        if (! $this->connections->isConnected() || $clientId === null || $encryptedRefresh === null) {
            throw new OAuthException('Connect before syncing.');
        }

        $tokens = $this->authServer->refreshAccessToken($clientId, $this->secrets->decrypt($encryptedRefresh), $this->config->resource);
        $this->controlPlane->registerInstallation($this->registrationPayload(), $tokens->accessToken);
        $this->connections->saveOAuth($clientId, $this->secrets->encrypt($tokens->refreshToken));
        $this->connections->markSynced();
        $this->audit->record('manifest_sync', 'success');
    }

    public function disconnect(): void
    {
        $this->connections->revoke();
        $this->pending->clear();
        $this->audit->record('disconnect', 'success');
    }

    /** @return array<string, mixed> */
    private function registrationPayload(): array
    {
        $identity = $this->identity->identity();

        return [
            'integration' => 'wordpress',
            'plugin_version' => $identity->pluginVersion,
            'site' => $identity->toArray(),
            'bridge_url' => rest_url('tropikal-connect/v1/bridge'),
            'manifest_url' => rest_url('tropikal-connect/v1/manifest'),
            'api_base_url' => rest_url('tropikal-connect/v1'),
            'manifest' => $this->schema->manifest($identity)->toArray(),
        ];
    }

    /**
     * The redirect URI carries its own query (WordPress admin-post.php routes on
     * ?action=…), so we validate the endpoint exactly (scheme/host/port/path and
     * every configured query param) while allowing the appended code + state.
     */
    private function matchesRedirect(string $callbackUrl): bool
    {
        $expected = wp_parse_url($this->config->redirectUri);
        $actual = wp_parse_url($callbackUrl);
        if (! is_array($expected) || ! is_array($actual)) {
            return false;
        }

        foreach (['scheme', 'host', 'port', 'path'] as $part) {
            if (($expected[$part] ?? null) !== ($actual[$part] ?? null)) {
                return false;
            }
        }

        parse_str((string) ($expected['query'] ?? ''), $expectedQuery);
        parse_str((string) ($actual['query'] ?? ''), $actualQuery);
        foreach ($expectedQuery as $key => $value) {
            if (! array_key_exists($key, $actualQuery) || ! hash_equals((string) $value, (string) $actualQuery[$key])) {
                return false;
            }
        }

        return true;
    }
}
