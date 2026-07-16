<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Execution;

use Tropikal\Connect\WordPress\Dto\BusinessObjectDescriptor;
use Tropikal\Connect\WordPress\Exception\ValidationException;

final readonly class ContentWriter
{
    public function __construct(private ContentReader $reader)
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(BusinessObjectDescriptor $object, array $payload): array
    {
        $id = wp_insert_post([
            'post_type' => $object->key,
            'post_title' => (string) ($payload['post_title'] ?? 'Untitled draft'),
            'post_content' => (string) ($payload['post_content'] ?? ''),
            'post_excerpt' => (string) ($payload['post_excerpt'] ?? ''),
            'post_name' => (string) ($payload['post_name'] ?? ''),
            'post_status' => 'draft',
        ], true);

        if (is_wp_error($id)) {
            throw new ValidationException((string) $id->get_error_message());
        }

        $this->writeMeta((int) $id, $payload);

        return $this->reader->get($object, (int) $id) ?? ['id' => (int) $id];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function update(BusinessObjectDescriptor $object, int $id, array $payload): array
    {
        $post = get_post($id);
        if (! is_object($post) || (string) ($post->post_type ?? '') !== $object->key) {
            throw new ValidationException('Content record was not found.');
        }

        if ((string) ($post->post_status ?? '') === 'publish') {
            return $this->createDraftProposal($object, $post, $payload);
        }

        $update = ['ID' => $id];
        foreach ($this->postFields($payload) as $field => $value) {
            $update[$field] = $value;
        }

        $updated = wp_update_post($update, true);
        if (is_wp_error($updated)) {
            throw new ValidationException((string) $updated->get_error_message());
        }

        $this->writeMeta($id, $payload);

        return $this->reader->get($object, $id) ?? ['id' => $id];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function createDraftProposal(BusinessObjectDescriptor $object, object $post, array $payload): array
    {
        $draft = [
            'post_type' => $object->key,
            'post_parent' => (int) ($post->ID ?? 0),
            'post_status' => 'draft',
            'post_title' => (string) ($payload['post_title'] ?? $post->post_title ?? ''),
            'post_content' => (string) ($payload['post_content'] ?? $post->post_content ?? ''),
            'post_excerpt' => (string) ($payload['post_excerpt'] ?? $post->post_excerpt ?? ''),
            'post_name' => (string) ($payload['post_name'] ?? ''),
        ];

        $id = wp_insert_post($draft, true);
        if (is_wp_error($id)) {
            throw new ValidationException((string) $id->get_error_message());
        }

        $record = $this->reader->get($object, (int) $id) ?? ['id' => (int) $id];
        $record['proposal_for'] = (int) ($post->ID ?? 0);

        return $record;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function postFields(array $payload): array
    {
        $map = [];
        foreach (['post_title', 'post_content', 'post_excerpt', 'post_name', 'post_status'] as $field) {
            if (array_key_exists($field, $payload)) {
                $map[$field] = (string) $payload[$field];
            }
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeMeta(int $id, array $payload): void
    {
        foreach ($payload as $key => $value) {
            if (str_starts_with((string) $key, 'meta.')) {
                update_post_meta($id, substr($key, 5), $value);
            }
        }
    }
}
