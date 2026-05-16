<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Execution;

use Tropikal\Connect\WordPress\Dto\BusinessObjectDescriptor;

final class ContentReader
{
    /**
     * @return array<string, mixed>|null
     */
    public function get(BusinessObjectDescriptor $object, int $id): ?array
    {
        $post = get_post($id);
        if (! is_object($post) || (string) ($post->post_type ?? '') !== $object->key) {
            return null;
        }

        return $this->normalize($post);
    }

    /**
     * @param object $post
     * @return array<string, mixed>
     */
    public function normalize(object $post): array
    {
        $id = (int) ($post->ID ?? 0);

        return [
            'id' => $id,
            'post_title' => (string) ($post->post_title ?? ''),
            'post_content' => (string) ($post->post_content ?? ''),
            'post_excerpt' => (string) ($post->post_excerpt ?? ''),
            'post_status' => (string) ($post->post_status ?? ''),
            'post_name' => (string) ($post->post_name ?? ''),
            'featured_media' => (int) get_post_thumbnail_id($id),
            'taxonomies' => $this->taxonomies($id, (string) ($post->post_type ?? '')),
            'created_at' => (string) ($post->post_date_gmt ?? ''),
            'updated_at' => (string) ($post->post_modified_gmt ?? ''),
            'published_at' => (string) ($post->post_date_gmt ?? ''),
            'permalink' => (string) get_permalink($id),
            'author_name' => (string) get_the_author_meta('display_name', (int) ($post->post_author ?? 0)),
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function taxonomies(int $id, string $postType): array
    {
        $terms = [];
        foreach (get_object_taxonomies($postType) as $taxonomy) {
            $postTerms = get_the_terms($id, (string) $taxonomy);
            if (! is_array($postTerms)) {
                continue;
            }
            $terms[(string) $taxonomy] = array_values(array_map(
                static fn (object $term): string => (string) ($term->slug ?? $term->name ?? ''),
                $postTerms,
            ));
        }

        return $terms;
    }
}
