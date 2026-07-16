<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tropikal\Connect\WordPress\Discovery\BusinessObjectDiscoveryService;
use Tropikal\Connect\WordPress\Execution\ContentReader;
use Tropikal\Connect\WordPress\Execution\ContentWriter;
use Tropikal\Connect\WordPress\Execution\FieldPolicy;

/**
 * "Update business objects the same way": a registered public custom field
 * (post meta with show_in_rest) is discoverable, writable through the field
 * policy, and round-trips — so a TROPIKAL job can update it like any field.
 */
final class CustomFieldTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['wp_options'] = [];
        $GLOBALS['wp_posts'] = [];
        $GLOBALS['wp_next_id'] = 100;
        $GLOBALS['wp_post_meta'] = [];
        $GLOBALS['wp_registered_meta'] = [
            'post' => [
                'subtitle' => ['show_in_rest' => true, 'type' => 'string', 'single' => true],
                '_secret_internal' => ['show_in_rest' => true, 'type' => 'string'], // protected: excluded
                'hidden' => ['show_in_rest' => false, 'type' => 'string'], // not REST: excluded
            ],
        ];
    }

    public function testCustomFieldIsDiscoveredAsWritable(): void
    {
        $post = (new BusinessObjectDiscoveryService())->discover()['post'];
        $keys = array_map(static fn ($f): string => $f->key, $post->fields);

        self::assertContains('meta.subtitle', $keys);
        self::assertNotContains('meta._secret_internal', $keys, 'protected meta excluded');
        self::assertNotContains('meta.hidden', $keys, 'non-REST meta excluded');

        $subtitle = array_values(array_filter($post->fields, static fn ($f): bool => $f->key === 'meta.subtitle'))[0];
        self::assertTrue($subtitle->writable);
    }

    public function testJobCanCreateAndReadACustomField(): void
    {
        $discovery = new BusinessObjectDiscoveryService();
        $object = $discovery->discover()['post'];
        $reader = new ContentReader();
        $writer = new ContentWriter($reader);
        $policy = new FieldPolicy();

        // field policy accepts the declared writable custom field
        $validated = $policy->validateWrite($object, ['post_title' => 'Hello', 'meta.subtitle' => 'A subtitle']);
        self::assertArrayHasKey('meta.subtitle', $validated);

        // and rejects an undeclared field (fail closed)
        try {
            $policy->validateWrite($object, ['not_a_field' => 'x']);
            self::fail('expected a validation error for an undeclared field');
        } catch (\Tropikal\Connect\WordPress\Exception\ValidationException) {
        }

        $created = $writer->create($object, $validated);
        self::assertSame('A subtitle', $created['meta.subtitle']);
    }
}
