<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tropikal\Connect\WordPress\Discovery\BusinessObjectDiscoveryService;
use Tropikal\Connect\WordPress\Discovery\ExtensionDetector;
use Tropikal\Connect\WordPress\Discovery\WordPressSchemaMapper;
use Tropikal\Connect\WordPress\Storage\GrantRepository;
use Tropikal\Connect\WordPress\Storage\OptionsRepository;
use Tropikal\Connect\WordPress\WordPress\SiteIdentityProvider;

final class DiscoveryTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['wp_options'] = [];
    }

    public function testDiscoveryFindsSafeWordPressObjectsAndExcludesSecretShapedObjects(): void
    {
        $objects = (new BusinessObjectDiscoveryService())->discover();

        self::assertArrayHasKey('post', $objects);
        self::assertArrayHasKey('page', $objects);
        self::assertArrayHasKey('attachment', $objects);
        self::assertArrayNotHasKey('user_token', $objects);
        self::assertContains('delete', $objects['post']->operations);
    }

    public function testEmptyGrantsExposeNothingInManifest(): void
    {
        $manifest = $this->schema()->manifest((new SiteIdentityProvider())->identity())->toArray();

        self::assertSame([], $manifest['resources']);
    }

    public function testReadWriteDeleteGrantsCreateIndependentOperations(): void
    {
        $grants = new GrantRepository(new OptionsRepository());
        $grants->set('post', 'write', true);

        $manifest = $this->schema($grants)->manifest((new SiteIdentityProvider())->identity())->toArray();
        $operations = array_column($manifest['resources'][0]['operations'], 'operation');

        self::assertContains('wordpress.resource.create', $operations);
        self::assertContains('wordpress.resource.update', $operations);
        self::assertNotContains('wordpress.resource.delete', $operations);

        $grants->set('post', 'delete', true);
        $manifest = $this->schema($grants)->manifest((new SiteIdentityProvider())->identity())->toArray();
        $operations = array_column($manifest['resources'][0]['operations'], 'operation');

        self::assertContains('wordpress.resource.delete', $operations);
    }

    private function schema(?GrantRepository $grants = null): WordPressSchemaMapper
    {
        return new WordPressSchemaMapper(
            new BusinessObjectDiscoveryService(),
            $grants ?? new GrantRepository(new OptionsRepository()),
            new ExtensionDetector(),
        );
    }
}
