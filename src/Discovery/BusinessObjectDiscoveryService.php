<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Discovery;

use Tropikal\Connect\WordPress\Dto\BusinessObjectDescriptor;
use Tropikal\Connect\WordPress\Dto\FieldDescriptor;
use TropikalAI\Connect\Domain\Security\SensitiveData;

final readonly class BusinessObjectDiscoveryService
{
    private const SAFE_FIELDS = [
        'id' => ['ID', 'integer', true, false],
        'post_title' => ['Title', 'string', true, true],
        'post_content' => ['Content', 'string', true, true],
        'post_excerpt' => ['Excerpt', 'string', true, true],
        'post_status' => ['Status', 'string', true, true],
        'post_name' => ['Slug', 'string', true, true],
        'featured_media' => ['Featured media', 'integer', true, true],
        'taxonomies' => ['Taxonomies', 'object', true, true],
        'created_at' => ['Created at', 'datetime', true, false],
        'updated_at' => ['Updated at', 'datetime', true, false],
        'published_at' => ['Published at', 'datetime', true, false],
        'permalink' => ['Permalink', 'url', true, false],
        'author_name' => ['Author', 'string', true, false],
    ];

    /**
     * @return array<string, BusinessObjectDescriptor>
     */
    public function discover(): array
    {
        $objects = [];

        foreach (get_post_types(['show_ui' => true], 'objects') as $postType => $postTypeObject) {
            if (! is_string($postType) || ! is_object($postTypeObject) || ! $this->isSafePostType($postType, $postTypeObject)) {
                continue;
            }

            $label = is_string($postTypeObject->label ?? null) ? $postTypeObject->label : ucfirst(str_replace('_', ' ', $postType));
            $objects[$postType] = new BusinessObjectDescriptor(
                key: $postType,
                label: $label,
                kind: 'post_type',
                fields: $this->fieldsFor($postType),
                operations: ['list', 'search', 'get', 'create', 'update', 'draft_write', 'publish_approved', 'delete'],
                readable: true,
                writable: $postType !== 'attachment',
                deletable: true,
            );
        }

        if (! isset($objects['attachment'])) {
            $objects['attachment'] = new BusinessObjectDescriptor(
                key: 'attachment',
                label: 'Media',
                kind: 'media',
                fields: [
                    new FieldDescriptor('id', 'ID', 'integer', true, false),
                    new FieldDescriptor('title', 'Title', 'string', true, true),
                    new FieldDescriptor('filename', 'Filename', 'string', true, false),
                    new FieldDescriptor('mime_type', 'MIME type', 'string', true, false),
                    new FieldDescriptor('url', 'URL', 'url', true, false),
                    new FieldDescriptor('alt_text', 'Alt text', 'string', true, true),
                    new FieldDescriptor('created_at', 'Created at', 'datetime', true, false),
                    new FieldDescriptor('updated_at', 'Updated at', 'datetime', true, false),
                ],
                operations: ['list', 'get', 'upload', 'delete'],
                readable: true,
                writable: true,
                deletable: true,
            );
        }

        ksort($objects);

        return $objects;
    }

    private function isSafePostType(string $postType, object $postTypeObject): bool
    {
        if (SensitiveData::isSensitiveKey($postType)) {
            return false;
        }

        if (in_array($postType, ['user', 'users', 'wp_user', 'wp_users', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'wp_block'], true)) {
            return false;
        }

        return (bool) ($postTypeObject->public ?? false)
            || (bool) ($postTypeObject->show_ui ?? false)
            || in_array($postType, ['post', 'page', 'attachment'], true);
    }

    /**
     * @return list<FieldDescriptor>
     */
    private function fieldsFor(string $postType): array
    {
        $fields = [];
        foreach (self::SAFE_FIELDS as $key => [$label, $type, $readable, $writable]) {
            if (SensitiveData::isSensitiveKey($key)) {
                continue;
            }

            $fields[] = new FieldDescriptor($key, (string) $label, (string) $type, (bool) $readable, (bool) $writable);
        }

        foreach ($this->metaFields($postType) as $field) {
            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * Registered public custom fields (post meta declared with show_in_rest),
     * exposed as writable `meta.<key>` fields so a job can update them the same
     * way as built-in fields. Protected keys (leading underscore) and
     * secret-shaped keys are excluded.
     *
     * @return list<FieldDescriptor>
     */
    private function metaFields(string $postType): array
    {
        if (! function_exists('get_registered_meta_keys')) {
            return [];
        }

        $fields = [];
        foreach ((array) get_registered_meta_keys('post', $postType) as $metaKey => $args) {
            if ($metaKey === '' || $metaKey[0] === '_') {
                continue;
            }
            if (! is_array($args) || empty($args['show_in_rest']) || SensitiveData::isSensitiveKey($metaKey)) {
                continue;
            }

            $type = match ((string) ($args['type'] ?? 'string')) {
                'integer', 'number' => 'integer',
                'boolean' => 'boolean',
                'object', 'array' => 'object',
                default => 'string',
            };
            $fields[] = new FieldDescriptor('meta.' . $metaKey, ucfirst(str_replace('_', ' ', $metaKey)), $type, true, true);
        }

        return $fields;
    }
}
