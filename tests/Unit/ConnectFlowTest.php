<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tropikal\Connect\WordPress\Exception\OAuthException;
use Tropikal\Connect\WordPress\Setup\ConnectConfig;
use Tropikal\Connect\WordPress\Setup\ConnectFlow;
use Tropikal\Connect\WordPress\Setup\PendingAuthorizationStore;
use Tropikal\Connect\WordPress\Tests\Support\FakeAuthorizationServer;
use Tropikal\Connect\WordPress\Tests\Support\FakeControlPlane;
use Tropikal\Connect\WordPress\Discovery\BusinessObjectDiscoveryService;
use Tropikal\Connect\WordPress\Discovery\ExtensionDetector;
use Tropikal\Connect\WordPress\Discovery\WordPressSchemaMapper;
use Tropikal\Connect\WordPress\Security\SecretStore;
use Tropikal\Connect\WordPress\Storage\AuditLogRepository;
use Tropikal\Connect\WordPress\Storage\ConnectionRepository;
use Tropikal\Connect\WordPress\Storage\GrantRepository;
use Tropikal\Connect\WordPress\Storage\OptionsRepository;
use Tropikal\Connect\WordPress\WordPress\SiteIdentityProvider;

/**
 * One-click connect for WordPress, matching the Filament / n2n flow: begin()
 * registers an OAuth client (PKCE + hashed state) and returns the authorization
 * URL; complete() validates the callback, exchanges the code, registers the
 * installation on the control plane with the Bearer token, and stores ONLY the
 * control-plane-issued signing key — no local-secret fallback.
 */
final class ConnectFlowTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['wp_options'] = [];
        $GLOBALS['wpdb'] = new \FakeWpdb();
    }

    public function testBeginRegistersClientAndBuildsAuthorizationUrl(): void
    {
        [$flow, , $authServer] = $this->flow();

        $url = $flow->begin('7');

        $q = $this->query($url);
        self::assertStringStartsWith('https://id.example.test/oauth/authorize?', $url);
        self::assertSame('code', $q['response_type']);
        self::assertSame($authServer->issuedClientId, $q['client_id']);
        self::assertSame('https://example.com/wp-admin/admin-post.php?action=tropikal_connect_callback', $q['redirect_uri']);
        self::assertSame('S256', $q['code_challenge_method']);
        self::assertNotEmpty($q['state']);
        self::assertNotEmpty($q['code_challenge']);
    }

    public function testCompleteStoresTheControlPlaneSigningKeyAndConnects(): void
    {
        [$flow, $deps, $authServer, $controlPlane] = $this->flow();
        $url = $flow->begin('7');
        $state = $this->query($url)['state'];
        $code = $authServer->issueCode($this->query($url)['code_challenge']);

        $flow->complete($state, $code, 'https://example.com/wp-admin/admin-post.php?action=tropikal_connect_callback&code=' . $code . '&state=' . $state);

        $connections = $deps['connections'];
        self::assertTrue($connections->isConnected());
        self::assertSame('inst_oauth_1', $connections->installationId());
        // the stored secret is the control-plane key, decryptable
        self::assertSame($controlPlane->issuedSigningKey, $deps['secrets']->decrypt((string) $connections->encryptedSecret()));
        // pending authorization cleared
        self::assertNull((new PendingAuthorizationStore(new OptionsRepository(), $deps['secrets']))->load());
        // registration used the bearer token + carried the manifest
        self::assertSame($authServer->issuedAccessToken, $controlPlane->seenAccessToken);
        self::assertArrayHasKey('manifest', $controlPlane->seenPayload);
    }

    public function testCompleteFailsClosedWhenNoServerKey(): void
    {
        [$flow, $deps, $authServer, $controlPlane] = $this->flow();
        $controlPlane->response = ['installation_id' => 'inst_x']; // NO server signing key
        $url = $flow->begin('7');
        $state = $this->query($url)['state'];
        $code = $authServer->issueCode($this->query($url)['code_challenge']);

        try {
            $flow->complete($state, $code, 'https://example.com/wp-admin/admin-post.php?action=tropikal_connect_callback');
            self::fail('expected OAuthException');
        } catch (OAuthException) {
        }

        self::assertFalse($deps['connections']->isConnected(), 'no half-connected state persisted');
    }

    public function testCompleteRejectsForgedState(): void
    {
        [$flow] = $this->flow();
        $flow->begin('7');

        $this->expectException(OAuthException::class);
        $flow->complete('forged', 'any', 'https://example.com/wp-admin/admin-post.php?action=tropikal_connect_callback');
    }

    public function testCompleteRejectsMismatchedRedirect(): void
    {
        [$flow, , $authServer] = $this->flow();
        $url = $flow->begin('7');
        $state = $this->query($url)['state'];
        $code = $authServer->issueCode($this->query($url)['code_challenge']);

        $this->expectException(OAuthException::class);
        $flow->complete($state, $code, 'https://evil.example.test/wp-admin/admin-post.php?action=tropikal_connect_callback');
    }

    public function testSyncRefreshesTheTokenAndRepushesTheManifest(): void
    {
        [$flow, $deps, $authServer, $controlPlane] = $this->flow();
        $this->connect($flow, $authServer);
        $controlPlane->seenAccessToken = '';

        $flow->sync();

        self::assertSame($authServer->issuedAccessToken, $controlPlane->seenAccessToken, 'sync used a refreshed token');
        self::assertSame('wp_rt_fake', $authServer->refreshedWith, 'sync used the stored refresh token');
        self::assertNotNull($deps['connections']->lastSyncAt());
    }

    public function testSyncRequiresAConnection(): void
    {
        [$flow] = $this->flow();
        $this->expectException(OAuthException::class);
        $flow->sync();
    }

    public function testDisconnectRevokesTheConnection(): void
    {
        [$flow, $deps, $authServer] = $this->flow();
        $this->connect($flow, $authServer);

        $flow->disconnect();

        self::assertFalse($deps['connections']->isConnected());
    }

    private function connect(ConnectFlow $flow, FakeAuthorizationServer $authServer): void
    {
        $url = $flow->begin('7');
        $state = $this->query($url)['state'];
        $code = $authServer->issueCode($this->query($url)['code_challenge']);
        $flow->complete($state, $code, 'https://example.com/wp-admin/admin-post.php?action=tropikal_connect_callback&code=' . $code . '&state=' . $state);
    }

    /** @return array{0: ConnectFlow, 1: array{connections: ConnectionRepository, secrets: SecretStore}, 2: FakeAuthorizationServer, 3: FakeControlPlane} */
    private function flow(): array
    {
        $options = new OptionsRepository();
        $secrets = new SecretStore();
        $connections = new ConnectionRepository($options);
        $grants = new GrantRepository($options);
        $discovery = new BusinessObjectDiscoveryService();
        $schema = new WordPressSchemaMapper($discovery, $grants, new ExtensionDetector());
        $authServer = new FakeAuthorizationServer();
        $controlPlane = new FakeControlPlane();

        $flow = new ConnectFlow(
            $this->config(),
            new PendingAuthorizationStore($options, $secrets),
            $authServer,
            $controlPlane,
            $connections,
            $secrets,
            $grants,
            $schema,
            new SiteIdentityProvider(),
            new AuditLogRepository(),
        );

        return [$flow, ['connections' => $connections, 'secrets' => $secrets], $authServer, $controlPlane];
    }

    private function config(): ConnectConfig
    {
        return new ConnectConfig(
            siteUrl: 'https://example.com',
            authorizationServerUrl: 'https://id.example.test',
            controlPlaneUrl: 'https://app.example.test',
            redirectUri: 'https://example.com/wp-admin/admin-post.php?action=tropikal_connect_callback',
            scopes: 'connect.install',
            resource: 'https://app.example.test',
            clientName: 'Example WP',
            defaultGrants: ['post' => ['read', 'create', 'update', 'delete']],
        );
    }

    /** @return array<string, string> */
    private function query(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

        return array_map(strval(...), $q);
    }
}
