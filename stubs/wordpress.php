<?php

declare(strict_types=1);

if (! defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (! defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

class WP_REST_Request
{
    public function get_body(): string {}
    public function get_headers(): array {}
    public function get_route(): string {}
    public function get_query_params(): array {}
    public function get_method(): string {}
}

class WP_REST_Response
{
    public function __construct(mixed $data = null, int $status = 200) {}
}

class WP_Error
{
    public function get_error_message(): string {}
}

class WP_Query
{
    public array $posts = [];
    public int $found_posts = 0;
    public int $max_num_pages = 1;
    public function __construct(array $query = []) {}
}

function __return_true(): bool {}
function __(string $text, string $domain = 'default'): string {}
function esc_html__(string $text, string $domain = 'default'): string {}
function esc_html(string $text): string {}
function esc_attr(string $text): string {}
function esc_url(string $text): string {}
function checked(bool $checked, bool $current = true, bool $display = true): string {}
function add_action(string $hook_name, callable|string|array $callback, int $priority = 10, int $accepted_args = 1): true {}
function add_options_page(string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback): string {}
function register_activation_hook(string $file, callable $callback): void {}
function register_deactivation_hook(string $file, callable $callback): void {}
function register_rest_route(string $namespace, string $route, array $args = [], bool $override = false): bool {}
function current_user_can(string $capability): bool {}
function get_role(string $role): ?object {}
function wp_die(string $message = ''): never {}
function check_admin_referer(string $action = '-1', string $query_arg = '_wpnonce'): int|false {}
function wp_nonce_field(string $action = '-1', string $name = '_wpnonce', bool $referer = true, bool $display = true): string {}
function sanitize_key(string $key): string {}
function sanitize_text_field(string $str): string {}
function sanitize_file_name(string $filename): string {}
function wp_unslash(mixed $value): mixed {}
function wp_safe_redirect(string $location, int $status = 302): bool {}
function admin_url(string $path = ''): string {}
function plugin_dir_path(string $file): string {}
function plugin_dir_url(string $file): string {}
function wp_next_scheduled(string $hook): int|false {}
function wp_schedule_event(int $timestamp, string $recurrence, string $hook, array $args = [], bool $wp_error = false): bool|WP_Error {}
function wp_clear_scheduled_hook(string $hook, array $args = [], bool $wp_error = false): int|false|WP_Error {}
function get_option(string $option, mixed $default = false): mixed {}
function update_option(string $option, mixed $value, mixed $autoload = null): bool {}
function delete_option(string $option): bool {}
function set_transient(string $transient, mixed $value, int $expiration = 0): bool {}
function get_transient(string $transient): mixed {}
function delete_transient(string $transient): bool {}
function wp_json_encode(mixed $value, int $flags = 0, int $depth = 512): string|false {}
function get_current_user_id(): int {}
function get_bloginfo(string $show = '', string $filter = 'raw'): string {}
function home_url(string $path = '', ?string $scheme = null): string {}
function site_url(string $path = '', ?string $scheme = null): string {}
function rest_url(string $path = '', ?string $scheme = null): string {}
function wp_get_environment_type(): string {}
function is_multisite(): bool {}
function get_post_types(array|string $args = [], string $output = 'names', string $operator = 'and'): array {}
function get_post_type_object(string $post_type): ?object {}
function get_taxonomies(array $args = [], string $output = 'names', string $operator = 'and'): array {}
function get_taxonomy(string $taxonomy): ?object {}
function post_type_supports(string $post_type, string $feature): bool {}
function get_object_taxonomies(string|object $object, string $output = 'names'): array {}
function get_post(int|object|null $post = null, string $output = 'OBJECT', string $filter = 'raw'): mixed {}
function get_post_thumbnail_id(int|object|null $post = null): int|false {}
function get_the_terms(int|object $post, string $taxonomy): array|false|WP_Error {}
function get_permalink(int|object $post = 0, bool $leavename = false): string|false {}
function get_the_author_meta(string $field = '', int|false $user_id = false): string {}
function wp_strip_all_tags(string $text, bool $remove_breaks = false): string {}
function wp_insert_post(array $postarr, bool $wp_error = false, bool $fire_after_hooks = true): int|WP_Error {}
function wp_update_post(array $postarr = [], bool $wp_error = false, bool $fire_after_hooks = true): int|WP_Error {}
function wp_trash_post(int $post_id): object|false|null {}
function is_wp_error(mixed $thing): bool {}
function get_attached_file(int $attachment_id, bool $unfiltered = false): string|false {}
function wp_get_attachment_url(int $attachment_id): string|false {}
function get_post_meta(int $post_id, string $key = '', bool $single = false): mixed {}
function update_post_meta(int $post_id, string $meta_key, mixed $meta_value, mixed $prev_value = ''): int|bool {}
function get_allowed_mime_types(?int $user = null): array {}
function wp_upload_bits(string $name, ?string $deprecated, string $bits, ?string $time = null): array {}
function wp_insert_attachment(array $args, string|false $file = false, int $parent_post_id = 0, bool $wp_error = false, bool $fire_after_hooks = true): int|WP_Error {}
function wp_remote_post(string $url, array $args = []): array|WP_Error {}
function wp_remote_retrieve_response_code(array|WP_Error $response): int|string {}
function wp_remote_retrieve_body(array|WP_Error $response): string {}
function wp_parse_url(string $url, int $component = -1): mixed {}
function apply_filters(string $hook_name, mixed $value, mixed ...$args): mixed {}
function dbDelta(string|array $queries = '', bool $execute = true): array {}
