<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

define('ABSPATH', __DIR__);
define('AUTH_KEY', str_repeat('a', 64));
define('SECURE_AUTH_KEY', str_repeat('b', 64));
define('LOGGED_IN_KEY', str_repeat('c', 64));
define('NONCE_KEY', str_repeat('d', 64));
define('TROPIKAL_CONNECT_WORDPRESS_VERSION', '0.1.0');
define('HOUR_IN_SECONDS', 3600);
define('ARRAY_A', 'ARRAY_A');

$GLOBALS['wp_options'] = [];
$GLOBALS['wp_posts'] = [];
$GLOBALS['wp_next_id'] = 100;
$GLOBALS['wp_current_user_can'] = true;

final class WP_Error
{
    public function __construct(private string $message) {}
    public function get_error_message(): string { return $this->message; }
}

final class WP_REST_Response
{
    public function __construct(public mixed $data = null, public int $status = 200) {}
    public function get_data(): mixed { return $this->data; }
    public function get_status(): int { return $this->status; }
}

final class WP_REST_Request
{
    public function __construct(private string $body = '', private array $headers = [], private array $query = []) {}
    public function get_body(): string { return $this->body; }
    public function get_headers(): array { return $this->headers; }
    public function get_route(): string { return '/tropikal-connect/v1/bridge'; }
    public function get_query_params(): array { return $this->query; }
    public function get_method(): string { return 'POST'; }
}

final class WP_Query
{
    public array $posts;
    public int $found_posts;
    public int $max_num_pages;

    public function __construct(array $args = [])
    {
        $type = (string) ($args['post_type'] ?? 'post');
        $status = (string) ($args['post_status'] ?? 'publish');
        $search = strtolower((string) ($args['s'] ?? ''));
        $limit = max(1, (int) ($args['posts_per_page'] ?? 20));
        $this->posts = array_values(array_filter($GLOBALS['wp_posts'], static function (object $post) use ($type, $status, $search): bool {
            if ((string) $post->post_type !== $type || (string) $post->post_status !== $status) {
                return false;
            }
            if ($search === '') {
                return true;
            }

            return str_contains(strtolower((string) $post->post_title), $search)
                || str_contains(strtolower((string) $post->post_content), $search);
        }));
        $this->found_posts = count($this->posts);
        $this->posts = array_slice($this->posts, 0, $limit);
        $this->max_num_pages = 1;
    }
}

final class FakeWpdb
{
    public string $prefix = 'wp_';
    public array $rows = [];
    public function get_charset_collate(): string { return 'DEFAULT CHARSET=utf8mb4'; }
    public function insert(string $table, array $data): int|false
    {
        if (str_contains($table, 'nonces')) {
            foreach ($this->rows[$table] ?? [] as $row) {
                if ($row['installation_id'] === $data['installation_id'] && $row['nonce_hash'] === $data['nonce_hash']) {
                    return false;
                }
            }
        }
        $this->rows[$table][] = ['id' => count($this->rows[$table] ?? []) + 1, ...$data];

        return 1;
    }
    public function query(string $query): int|false { unset($query); return 1; }
    public function prepare(string $query, mixed ...$args): string { return vsprintf(str_replace('%s', "'%s'", $query), $args); }
    public function get_results(string $query, string $output = ''): array
    {
        unset($query, $output);
        $rows = $this->rows[$this->prefix . 'tropikal_connect_audit_logs'] ?? [];

        return array_reverse($rows);
    }
}

$GLOBALS['wpdb'] = new FakeWpdb();

function __return_true(): bool { return true; }
function __(string $text, string $domain = 'default'): string { unset($domain); return $text; }
function esc_html__(string $text, string $domain = 'default'): string { unset($domain); return $text; }
function esc_html(string $text): string { return htmlspecialchars($text, ENT_QUOTES); }
function esc_attr(string $text): string { return htmlspecialchars($text, ENT_QUOTES); }
function esc_url(string $text): string { return htmlspecialchars($text, ENT_QUOTES); }
function checked(bool $checked, bool $current = true, bool $display = true): string { unset($display); return $checked === $current ? 'checked="checked"' : ''; }
function add_action(string $hook_name, callable|string|array $callback, int $priority = 10, int $accepted_args = 1): true { unset($hook_name, $callback, $priority, $accepted_args); return true; }
function add_options_page(string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback): string { unset($page_title, $menu_title, $capability, $menu_slug, $callback); return 'settings_page_tropikal-connect'; }
function register_activation_hook(string $file, callable $callback): void { unset($file, $callback); }
function register_deactivation_hook(string $file, callable $callback): void { unset($file, $callback); }
function register_rest_route(string $namespace, string $route, array $args = [], bool $override = false): bool { unset($namespace, $route, $args, $override); return true; }
function current_user_can(string $capability): bool { unset($capability); return (bool) $GLOBALS['wp_current_user_can']; }
function get_role(string $role): ?object { unset($role); return new class { public function add_cap(string $cap): void { unset($cap); } }; }
function wp_die(string $message = ''): never { throw new RuntimeException($message); }
function check_admin_referer(string $action = '-1', string $query_arg = '_wpnonce'): int|false { unset($action, $query_arg); return 1; }
function wp_nonce_field(string $action = '-1', string $name = '_wpnonce', bool $referer = true, bool $display = true): string { unset($action, $name, $referer, $display); return ''; }
function sanitize_key(string $key): string { return preg_replace('/[^a-z0-9_\\-]/', '', strtolower($key)) ?? ''; }
function sanitize_text_field(string $str): string { return trim(strip_tags($str)); }
function sanitize_file_name(string $filename): string { return basename($filename); }
function wp_unslash(mixed $value): mixed { return $value; }
function wp_safe_redirect(string $location, int $status = 302): bool { unset($location, $status); return true; }
function admin_url(string $path = ''): string { return 'https://example.com/wp-admin/' . ltrim($path, '/'); }
function plugin_dir_path(string $file): string { return dirname($file) . '/'; }
function plugin_dir_url(string $file): string { unset($file); return 'https://example.com/wp-content/plugins/tropikal-connect-wordpress/'; }
function wp_next_scheduled(string $hook): int|false { unset($hook); return false; }
function wp_schedule_event(int $timestamp, string $recurrence, string $hook, array $args = [], bool $wp_error = false): bool|WP_Error { unset($timestamp, $recurrence, $hook, $args, $wp_error); return true; }
function wp_clear_scheduled_hook(string $hook, array $args = [], bool $wp_error = false): int|false|WP_Error { unset($hook, $args, $wp_error); return 1; }
function get_option(string $option, mixed $default = false): mixed { return $GLOBALS['wp_options'][$option] ?? $default; }
function update_option(string $option, mixed $value, mixed $autoload = null): bool { unset($autoload); $GLOBALS['wp_options'][$option] = $value; return true; }
function delete_option(string $option): bool { unset($GLOBALS['wp_options'][$option]); return true; }
function set_transient(string $transient, mixed $value, int $expiration = 0): bool { unset($expiration); $GLOBALS['wp_options']['_transient_' . $transient] = $value; return true; }
function get_transient(string $transient): mixed { return $GLOBALS['wp_options']['_transient_' . $transient] ?? false; }
function delete_transient(string $transient): bool { unset($GLOBALS['wp_options']['_transient_' . $transient]); return true; }
function wp_json_encode(mixed $value, int $flags = 0, int $depth = 512): string|false { return json_encode($value, $flags, $depth); }
function get_current_user_id(): int { return 1; }
function get_bloginfo(string $show = '', string $filter = 'raw'): string { unset($filter); return $show === 'version' ? '6.8' : 'Example Site'; }
function home_url(string $path = '', ?string $scheme = null): string { unset($scheme); return 'https://example.com' . $path; }
function site_url(string $path = '', ?string $scheme = null): string { unset($scheme); return 'https://example.com' . $path; }
function rest_url(string $path = '', ?string $scheme = null): string { unset($scheme); return 'https://example.com/wp-json/' . ltrim($path, '/'); }
function wp_get_environment_type(): string { return 'local'; }
function is_multisite(): bool { return false; }
function get_post_types(array|string $args = [], string $output = 'names', string $operator = 'and'): array
{
    unset($args, $operator);
    $types = [
        'post' => (object) ['label' => 'Posts', 'public' => true, 'show_ui' => true],
        'page' => (object) ['label' => 'Pages', 'public' => true, 'show_ui' => true],
        'attachment' => (object) ['label' => 'Media', 'public' => true, 'show_ui' => true],
        'user_token' => (object) ['label' => 'Tokens', 'public' => true, 'show_ui' => true],
    ];

    return $output === 'objects' ? $types : array_keys($types);
}
function get_post_type_object(string $post_type): ?object { $types = get_post_types([], 'objects'); return $types[$post_type] ?? null; }
function get_taxonomies(array $args = [], string $output = 'names', string $operator = 'and'): array { unset($args, $output, $operator); return ['category', 'post_tag']; }
function get_taxonomy(string $taxonomy): ?object { return (object) ['label' => ucfirst($taxonomy)]; }
function post_type_supports(string $post_type, string $feature): bool { unset($post_type, $feature); return true; }
function get_object_taxonomies(string|object $object, string $output = 'names'): array { unset($object, $output); return ['category']; }
function get_post(int|object|null $post = null, string $output = 'OBJECT', string $filter = 'raw'): mixed { unset($output, $filter); return $GLOBALS['wp_posts'][(int) $post] ?? null; }
function get_post_thumbnail_id(int|object|null $post = null): int|false { unset($post); return 0; }
function get_the_terms(int|object $post, string $taxonomy): array|false|WP_Error { unset($post, $taxonomy); return []; }
function get_permalink(int|object $post = 0, bool $leavename = false): string|false { unset($leavename); return 'https://example.com/?p=' . (int) $post; }
function get_the_author_meta(string $field = '', int|false $user_id = false): string { unset($field, $user_id); return 'Editor'; }
function wp_strip_all_tags(string $text, bool $remove_breaks = false): string { unset($remove_breaks); return strip_tags($text); }
function wp_insert_post(array $postarr, bool $wp_error = false, bool $fire_after_hooks = true): int|WP_Error
{
    unset($wp_error, $fire_after_hooks);
    $id = ++$GLOBALS['wp_next_id'];
    $GLOBALS['wp_posts'][$id] = (object) [
        'ID' => $id,
        'post_type' => $postarr['post_type'] ?? 'post',
        'post_title' => $postarr['post_title'] ?? '',
        'post_content' => $postarr['post_content'] ?? '',
        'post_excerpt' => $postarr['post_excerpt'] ?? '',
        'post_status' => $postarr['post_status'] ?? 'draft',
        'post_name' => $postarr['post_name'] ?? '',
        'post_author' => 1,
        'post_date_gmt' => '2026-01-01 00:00:00',
        'post_modified_gmt' => '2026-01-01 00:00:00',
    ];

    return $id;
}
function wp_update_post(array $postarr = [], bool $wp_error = false, bool $fire_after_hooks = true): int|WP_Error
{
    unset($wp_error, $fire_after_hooks);
    $id = (int) ($postarr['ID'] ?? 0);
    if (! isset($GLOBALS['wp_posts'][$id])) {
        return new WP_Error('not found');
    }
    foreach ($postarr as $key => $value) {
        if ($key !== 'ID') {
            $GLOBALS['wp_posts'][$id]->{$key} = $value;
        }
    }

    return $id;
}
function wp_trash_post(int $post_id): object|false|null { return $GLOBALS['wp_posts'][$post_id] ?? false; }
function is_wp_error(mixed $thing): bool { return $thing instanceof WP_Error; }
function get_attached_file(int $attachment_id, bool $unfiltered = false): string|false { unset($unfiltered); return "/tmp/{$attachment_id}.jpg"; }
function wp_get_attachment_url(int $attachment_id): string|false { return "https://example.com/uploads/{$attachment_id}.jpg"; }
function get_post_meta(int $post_id, string $key = '', bool $single = false): mixed { unset($post_id, $key, $single); return ''; }
function update_post_meta(int $post_id, string $meta_key, mixed $meta_value, mixed $prev_value = ''): int|bool { unset($post_id, $meta_key, $meta_value, $prev_value); return 1; }
function get_allowed_mime_types(?int $user = null): array { unset($user); return ['jpg|jpeg' => 'image/jpeg', 'png' => 'image/png']; }
function wp_upload_bits(string $name, ?string $deprecated, string $bits, ?string $time = null): array { unset($deprecated, $bits, $time); return ['file' => '/tmp/' . $name, 'url' => 'https://example.com/uploads/' . $name, 'error' => false]; }
function wp_insert_attachment(array $args, string|false $file = false, int $parent_post_id = 0, bool $wp_error = false, bool $fire_after_hooks = true): int|WP_Error { unset($args, $file, $parent_post_id, $wp_error, $fire_after_hooks); return ++$GLOBALS['wp_next_id']; }
function wp_remote_post(string $url, array $args = []): array|WP_Error { unset($url, $args); return ['response' => ['code' => 200], 'body' => '{"installation_id":"inst_123","site_id":"site_123","key_id":"key_123","signing_secret":"server_secret"}']; }
function wp_remote_retrieve_response_code(array|WP_Error $response): int|string { return is_array($response) ? ($response['response']['code'] ?? 0) : 0; }
function wp_remote_retrieve_body(array|WP_Error $response): string { return is_array($response) ? (string) ($response['body'] ?? '') : ''; }
function wp_parse_url(string $url, int $component = -1): mixed { return parse_url($url, $component); }
function apply_filters(string $hook_name, mixed $value, mixed ...$args): mixed { unset($hook_name, $args); return $value; }
function dbDelta(string|array $queries = '', bool $execute = true): array { unset($queries, $execute); return []; }
