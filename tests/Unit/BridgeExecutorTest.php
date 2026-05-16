<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tropikal\Connect\WordPress\Discovery\BusinessObjectDiscoveryService;
use Tropikal\Connect\WordPress\Dto\BridgeRequest;
use Tropikal\Connect\WordPress\Execution\ApprovedPublisher;
use Tropikal\Connect\WordPress\Execution\BridgeExecutor;
use Tropikal\Connect\WordPress\Execution\ContentDeleter;
use Tropikal\Connect\WordPress\Execution\ContentReader;
use Tropikal\Connect\WordPress\Execution\ContentSearcher;
use Tropikal\Connect\WordPress\Execution\ContentWriter;
use Tropikal\Connect\WordPress\Execution\FieldPolicy;
use Tropikal\Connect\WordPress\Execution\MediaReader;
use Tropikal\Connect\WordPress\Execution\MediaWriter;
use Tropikal\Connect\WordPress\Security\SecretStore;
use Tropikal\Connect\WordPress\Storage\AuditLogRepository;
use Tropikal\Connect\WordPress\Storage\ConnectionRepository;
use Tropikal\Connect\WordPress\Storage\GrantRepository;
use Tropikal\Connect\WordPress\Storage\OptionsRepository;

final class BridgeExecutorTest extends TestCase
{
    private OptionsRepository $options;

    private GrantRepository $grants;

    protected function setUp(): void
    {
        $GLOBALS['wp_options'] = [];
        $GLOBALS['wp_posts'] = [];
        $GLOBALS['wp_next_id'] = 100;
        $GLOBALS['wpdb'] = new \FakeWpdb();
        $this->options = new OptionsRepository();
        $this->grants = new GrantRepository($this->options);

        (new ConnectionRepository($this->options))->saveRegistration(
            ['installation_id' => 'inst_123', 'site_id' => 'site_123', 'key_id' => 'key_123'],
            (new SecretStore())->encrypt('server-secret'),
            '1',
        );
    }

    public function testReadGrantAllowsSearchAndProjectsSafeFields(): void
    {
        $this->grants->set('post', 'read', true);
        wp_insert_post(['post_type' => 'post', 'post_title' => 'Hello', 'post_content' => 'World', 'post_status' => 'publish']);

        $response = $this->executor()->execute(new BridgeRequest('wordpress.resource.search', 'post', ['query' => 'Hello']));

        self::assertSame(200, $response->statusCode);
        self::assertSame('Hello', $response->payload['data']['records'][0]['title']);
    }

    public function testWriteGrantCreatesDraftButDoesNotAllowUnknownFields(): void
    {
        $this->grants->set('post', 'write', true);

        $response = $this->executor()->execute(new BridgeRequest('wordpress.resource.create', 'post', [
            'post_title' => 'Draft',
            'access_token' => 'bad',
        ]));

        self::assertSame(422, $response->statusCode);
        self::assertSame('validation_error', $response->payload['error']['code']);

        $response = $this->executor()->execute(new BridgeRequest('wordpress.resource.create', 'post', [
            'post_title' => 'Draft',
        ]));

        self::assertSame(201, $response->statusCode);
        self::assertSame('draft', $response->payload['data']['post_status']);
    }

    public function testWriteGrantDoesNotAllowDelete(): void
    {
        $this->grants->set('post', 'write', true);
        $id = wp_insert_post(['post_type' => 'post', 'post_title' => 'Delete me', 'post_status' => 'publish']);

        $response = $this->executor()->execute(new BridgeRequest('wordpress.resource.delete', 'post', ['id' => $id]));

        self::assertSame(403, $response->statusCode);
        self::assertSame('grant_denied', $response->payload['error']['code']);
    }

    public function testDeleteGrantAllowsTrash(): void
    {
        $this->grants->set('post', 'delete', true);
        $id = wp_insert_post(['post_type' => 'post', 'post_title' => 'Delete me', 'post_status' => 'publish']);

        $response = $this->executor()->execute(new BridgeRequest('wordpress.resource.delete', 'post', ['id' => $id]));

        self::assertSame(200, $response->statusCode);
        self::assertTrue($response->payload['data']['deleted']);
    }

    private function executor(): BridgeExecutor
    {
        $reader = new ContentReader();

        return new BridgeExecutor(
            new ConnectionRepository($this->options),
            $this->grants,
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
}
