<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Execution;

use Tropikal\Connect\WordPress\Dto\BusinessObjectDescriptor;
use Tropikal\Connect\WordPress\Exception\ValidationException;

final readonly class ApprovedPublisher
{
    public function __construct(private ContentReader $reader)
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function publish(BusinessObjectDescriptor $object, array $payload): array
    {
        foreach (['id', 'approval_id', 'approved_by', 'approved_at'] as $field) {
            if (trim((string) ($payload[$field] ?? '')) === '') {
                throw new ValidationException("Publish approval requires {$field}.");
            }
        }

        $id = (int) $payload['id'];
        $post = get_post($id);
        if (! is_object($post) || (string) ($post->post_type ?? '') !== $object->key) {
            throw new ValidationException('Content record was not found.');
        }

        $updated = wp_update_post(['ID' => $id, 'post_status' => 'publish'], true);
        if (is_wp_error($updated)) {
            throw new ValidationException((string) $updated->get_error_message());
        }

        return $this->reader->get($object, $id) ?? ['id' => $id, 'post_status' => 'publish'];
    }
}
