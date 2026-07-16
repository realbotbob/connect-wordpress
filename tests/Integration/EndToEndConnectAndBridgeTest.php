<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tropikal\Connect\WordPress\Discovery\BusinessObjectDiscoveryService;
use Tropikal\Connect\WordPress\Discovery\ExtensionDetector;
use Tropikal\Connect\WordPress\Discovery\WordPressSchemaMapper;
use Tropikal\Connect\WordPress\Dto\BridgeRequest;
use Tropikal\Connect\WordPress\Dto\BridgeResponse;
use Tropikal\Connect\WordPress\Exception\InvalidSignatureException;
use Tropikal\Connect\WordPress\Execution\ApprovedPublisher;
use Tropikal\Connect\WordPress\Execution\BridgeExecutor;
use Tropikal\Connect\WordPress\Execution\ContentDeleter;
use Tropikal\Connect\WordPress\Execution\ContentReader;
use Tropikal\Connect\WordPress\Execution\ContentSearcher;
use Tropikal\Connect\WordPress\Execution\ContentWriter;
use Tropikal\Connect\WordPress\Execution\FieldPolicy;
use Tropikal\Connect\WordPress\Execution\MediaReader;
use Tropikal\Connect\WordPress\Execution\MediaWriter;
use Tropikal\Connect\WordPress\Security\NonceStore;
use Tropikal\Connect\WordPress\Security\SecretStore;
use Tropikal\Connect\WordPress\Security\SignatureVerifier;
use Tropikal\Connect\WordPress\Setup\ConnectConfig;
use Tropikal\Connect\WordPress\Setup\ConnectFlow;
use Tropikal\Connect\WordPress\Setup\PendingAuthorizationStore;
use Tropikal\Connect\WordPress\Storage\AuditLogRepository;
use Tropikal\Connect\WordPress\Storage\ConnectionRepository;
use Tropikal\Connect\WordPress\Storage\GrantRepository;
use Tropikal\Connect\WordPress\Storage\OptionsRepository;
use Tropikal\Connect\WordPress\Tests\Support\FakeAuthorizationServer;
use Tropikal\Connect\WordPress\Tests\Support\FakeControlPlane;
use Tropikal\Connect\WordPress\WordPress\SiteIdentityProvider;
use TropikalAI\Connect\Domain\Security\SignedRequest;

/**
 * The whole vertical slice, exercised end to end with the real classes:
 *
 *   1. One-click connect runs the real {@see ConnectFlow} (OAuth 2.1 + PKCE)
 *      against a mock TROPIKAL. The control plane returns the server signing
 *      key, which is encrypted at rest — no local-secret fallback.
 *   2. A TROPIKAL job then drives the bridge exactly as production would: every
 *      call is a real `tropikal-ai/connect` {@see SignedRequest}, signed with
 *      the key issued at connect, verified by the real {@see SignatureVerifier},
 *      and executed by the real {@see BridgeExecutor} against a Post — creating,
 *      reading, and updating a registered custom field.
 *
 * Only the external TROPIKAL authorization server and control plane are mocked
 * (they require live credentials); the connect handshake, canonical signing,
 * verification, replay protection, grants, field policy, and content mutation
 * are all the production code paths.
 */
final class EndToEndConnectAndBridgeTest extends TestCase
{
    private const BRIDGE_PATH = '/wp-json/tropikal-connect/v1/bridge';

    private OptionsRepository $options;

    private string $installationId;

    private string $signingKey;

    protected function setUp(): void
    {
        $GLOBALS['wp_options'] = [];
        $GLOBALS['wp_posts'] = [];
        $GLOBALS['wp_post_meta'] = [];
        $GLOBALS['wp_next_id'] = 100;
        $GLOBALS['wpdb'] = new \FakeWpdb();
        $GLOBALS['wp_registered_meta'] = [
            'post' => [
                'subtitle' => ['show_in_rest' => true, 'type' => 'string', 'single' => true],
            ],
        ];

        $this->options = new OptionsRepository();
        [$this->installationId, $this->signingKey] = $this->connect();
    }

    public function testAJobConnectsThenReadsAndUpdatesACustomFieldOverSignedRequests(): void
    {
        // The connection is live and the signing key came from the control plane.
        self::assertTrue((new ConnectionRepository($this->options))->isConnected());
        self::assertSame('inst_oauth_1', $this->installationId);

        // 1. Create a draft Post carrying a registered custom field.
        $created = $this->signedExecute('wordpress.resource.create', 'post', [
            'post_title' => 'Launch note',
            'post_content' => 'Body',
            'meta.subtitle' => 'first cut',
        ]);
        self::assertSame(201, $created->statusCode);
        self::assertSame('draft', $created->payload['data']['post_status']);
        self::assertSame('first cut', $created->payload['data']['meta.subtitle']);
        $id = (int) $created->payload['data']['id'];

        // 2. Read it back — the custom field is projected.
        $fetched = $this->signedExecute('wordpress.resource.get', 'post', ['id' => $id]);
        self::assertSame(200, $fetched->statusCode);
        self::assertSame('first cut', $fetched->payload['data']['meta.subtitle']);

        // 3. Update the custom field the same way any field is updated.
        $updated = $this->signedExecute('wordpress.resource.update', 'post', [
            'id' => $id,
            'meta.subtitle' => 'second cut',
        ]);
        self::assertSame(200, $updated->statusCode);
        self::assertSame('second cut', $updated->payload['data']['meta.subtitle']);

        // 4. The change persisted.
        $refetched = $this->signedExecute('wordpress.resource.get', 'post', ['id' => $id]);
        self::assertSame('second cut', $refetched->payload['data']['meta.subtitle']);
    }

    public function testAWrongSigningKeyIsRejected(): void
    {
        $body = $this->encode('wordpress.resource.get', 'post', ['id' => 1]);
        $headers = SignedRequest::headers('not-the-control-plane-key', $this->installationId, 'POST', self::BRIDGE_PATH, [], $body);

        $this->expectException(InvalidSignatureException::class);
        $this->verifier()->verify('POST', self::BRIDGE_PATH, [], $body, $headers);
    }

    public function testAReplayedSignedRequestIsRejected(): void
    {
        $body = $this->encode('wordpress.resource.get', 'post', ['id' => 1]);
        $headers = SignedRequest::headers($this->signingKey, $this->installationId, 'POST', self::BRIDGE_PATH, [], $body);
        $verifier = $this->verifier();

        $verifier->verify('POST', self::BRIDGE_PATH, [], $body, $headers);

        $this->expectException(InvalidSignatureException::class);
        $verifier->verify('POST', self::BRIDGE_PATH, [], $body, $headers);
    }

    /**
     * Sign a bridge call with the connect-issued key, verify it through the real
     * verifier, then execute it — the exact sequence the REST controller runs.
     *
     * @param array<string, mixed> $payload
     */
    private function signedExecute(string $operation, string $resourceKey, array $payload): BridgeResponse
    {
        $body = $this->encode($operation, $resourceKey, $payload);
        $headers = SignedRequest::headers($this->signingKey, $this->installationId, 'POST', self::BRIDGE_PATH, [], $body);

        $this->verifier()->verify('POST', self::BRIDGE_PATH, [], $body, $headers);

        $data = json_decode($body, true);
        self::assertIsArray($data);

        return $this->executor()->execute(BridgeRequest::fromArray($data));
    }

    /** @param array<string, mixed> $payload */
    private function encode(string $operation, string $resourceKey, array $payload): string
    {
        return (string) json_encode([
            'operation' => $operation,
            'resource_key' => $resourceKey,
            'payload' => $payload,
        ]);
    }

    private function verifier(): SignatureVerifier
    {
        return new SignatureVerifier(
            new ConnectionRepository($this->options),
            new SecretStore(),
            new \TropikalAI\Connect\Application\SignedRequestVerifier(new NonceStore()),
        );
    }

    private function executor(): BridgeExecutor
    {
        $reader = new ContentReader();

        return new BridgeExecutor(
            new ConnectionRepository($this->options),
            new GrantRepository($this->options),
            new BusinessObjectDiscoveryService(),
            new FieldPolicy(),
            new ContentSearcher($reader),
            $reader,
            new ContentWriter($reader),
            new ApprovedPublisher($reader),
            new ContentDeleter(),
            new MediaReader(),
            new MediaWriter(),
            new AuditLogRepository(),
        );
    }

    /**
     * Run the real one-click connect handshake against a mock TROPIKAL and
     * return the issued [installationId, decrypted signing key].
     *
     * @return array{0: string, 1: string}
     */
    private function connect(): array
    {
        $secrets = new SecretStore();
        $connections = new ConnectionRepository($this->options);
        $grants = new GrantRepository($this->options);
        $discovery = new BusinessObjectDiscoveryService();
        $schema = new WordPressSchemaMapper($discovery, $grants, new ExtensionDetector());
        $authServer = new FakeAuthorizationServer();
        $controlPlane = new FakeControlPlane();

        $flow = new ConnectFlow(
            new ConnectConfig(
                siteUrl: 'https://example.com',
                authorizationServerUrl: 'https://id.example.test',
                controlPlaneUrl: 'https://app.example.test',
                redirectUri: 'https://example.com/wp-admin/admin-post.php?action=tropikal_connect_callback',
                scopes: 'connect.install',
                resource: 'https://app.example.test',
                clientName: 'Example WP',
                defaultGrants: ['post' => ['read', 'create', 'update', 'delete']],
            ),
            new PendingAuthorizationStore($this->options, $secrets),
            $authServer,
            $controlPlane,
            $connections,
            $secrets,
            $grants,
            $schema,
            new SiteIdentityProvider(),
            new AuditLogRepository(),
        );

        $url = $flow->begin('7');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $code = $authServer->issueCode((string) $q['code_challenge']);
        $flow->complete(
            (string) $q['state'],
            $code,
            'https://example.com/wp-admin/admin-post.php?action=tropikal_connect_callback&code=' . $code . '&state=' . $q['state'],
        );

        return [
            (string) $connections->installationId(),
            $secrets->decrypt((string) $connections->encryptedSecret()),
        ];
    }
}
