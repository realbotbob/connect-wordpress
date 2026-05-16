<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Execution;

final class MediaReader
{
    /**
     * @return array<string, mixed>|null
     */
    public function get(int $id): ?array
    {
        $post = get_post($id);
        if (! is_object($post) || (string) ($post->post_type ?? '') !== 'attachment') {
            return null;
        }

        return [
            'id' => $id,
            'title' => (string) ($post->post_title ?? ''),
            'filename' => basename((string) get_attached_file($id)),
            'mime_type' => (string) ($post->post_mime_type ?? ''),
            'url' => (string) wp_get_attachment_url($id),
            'alt_text' => (string) get_post_meta($id, '_wp_attachment_image_alt', true),
            'created_at' => (string) ($post->post_date_gmt ?? ''),
            'updated_at' => (string) ($post->post_modified_gmt ?? ''),
        ];
    }
}
