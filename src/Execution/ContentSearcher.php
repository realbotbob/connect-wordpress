<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Execution;

use Tropikal\Connect\WordPress\Dto\BusinessObjectDescriptor;

final readonly class ContentSearcher
{
    public function __construct(private ContentReader $reader)
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function search(BusinessObjectDescriptor $object, array $payload): array
    {
        $limit = max(1, min(100, (int) ($payload['limit'] ?? 20)));
        $page = max(1, (int) ($payload['page'] ?? 1));
        $status = (string) ($payload['status'] ?? 'publish');
        $query = trim((string) ($payload['query'] ?? $payload['search'] ?? ''));

        $wpQuery = new \WP_Query([
            'post_type' => $object->key,
            'post_status' => $status,
            'posts_per_page' => $limit,
            'paged' => $page,
            's' => $query,
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);

        $records = [];
        foreach ($wpQuery->posts as $post) {
            if (is_object($post)) {
                $records[] = $this->summary($this->reader->normalize($post));
            }
        }

        return [
            'records' => $records,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int) $wpQuery->found_posts,
                'pages' => (int) $wpQuery->max_num_pages,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function summary(array $record): array
    {
        return [
            'id' => $record['id'] ?? null,
            'title' => $record['post_title'] ?? '',
            'excerpt' => wp_strip_all_tags((string) ($record['post_excerpt'] ?: mb_substr((string) ($record['post_content'] ?? ''), 0, 240))),
            'status' => $record['post_status'] ?? '',
            'modified_at' => $record['updated_at'] ?? '',
            'permalink' => $record['permalink'] ?? '',
        ];
    }
}
