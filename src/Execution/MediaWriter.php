<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Execution;

use Tropikal\Connect\WordPress\Exception\ValidationException;

final class MediaWriter
{
    private const MAX_BYTES = 10_485_760;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function upload(array $payload): array
    {
        $filename = sanitize_file_name((string) ($payload['filename'] ?? ''));
        $mime = (string) ($payload['mime_type'] ?? '');
        $content = base64_decode((string) ($payload['content_base64'] ?? ''), true);

        if ($filename === '' || $mime === '' || ! is_string($content)) {
            throw new ValidationException('Media upload requires filename, mime_type, and content_base64.');
        }
        if (strlen($content) > self::MAX_BYTES) {
            throw new ValidationException('Media upload is too large.');
        }
        if (! in_array($mime, get_allowed_mime_types(), true)) {
            throw new ValidationException('Media MIME type is not allowed.');
        }

        $upload = wp_upload_bits($filename, null, $content);
        if (! empty($upload['error'])) {
            throw new ValidationException((string) $upload['error']);
        }

        $id = wp_insert_attachment([
            'post_title' => sanitize_text_field((string) ($payload['title'] ?? pathinfo($filename, PATHINFO_FILENAME))),
            'post_mime_type' => $mime,
            'post_status' => 'inherit',
        ], (string) $upload['file']);

        if (is_wp_error($id)) {
            throw new ValidationException((string) $id->get_error_message());
        }

        if (isset($payload['alt_text'])) {
            update_post_meta((int) $id, '_wp_attachment_image_alt', sanitize_text_field((string) $payload['alt_text']));
        }

        return [
            'id' => (int) $id,
            'title' => (string) ($payload['title'] ?? pathinfo($filename, PATHINFO_FILENAME)),
            'filename' => $filename,
            'mime_type' => $mime,
            'url' => (string) ($upload['url'] ?? ''),
        ];
    }
}
