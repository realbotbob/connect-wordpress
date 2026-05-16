<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Admin;

final class Notices
{
    public function add(string $type, string $message): void
    {
        set_transient('tropikal_connect_notice', ['type' => $type, 'message' => $message], 30);
    }

    public function render(): void
    {
        $notice = get_transient('tropikal_connect_notice');
        if (! is_array($notice)) {
            return;
        }
        delete_transient('tropikal_connect_notice');

        $type = in_array($notice['type'] ?? '', ['success', 'error', 'warning', 'info'], true) ? (string) $notice['type'] : 'info';
        $message = (string) ($notice['message'] ?? '');

        echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }
}
