<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Execution;

use Tropikal\Connect\WordPress\Dto\BusinessObjectDescriptor;
use Tropikal\Connect\WordPress\Exception\ValidationException;

final class ContentDeleter
{
    public function delete(BusinessObjectDescriptor $object, int $id): bool
    {
        $post = get_post($id);
        if (! is_object($post) || (string) ($post->post_type ?? '') !== $object->key) {
            throw new ValidationException('Content record was not found.');
        }

        $deleted = wp_trash_post($id);

        return $deleted !== false && $deleted !== null;
    }
}
