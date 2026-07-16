<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Admin;

use Tropikal\Connect\WordPress\Security\PermissionGate;
use Tropikal\Connect\WordPress\Setup\ConnectFlow;

/**
 * The one-click connect surface:
 *   admin_post_tropikal_connect_connect   -> begin OAuth (redirect to TROPIKAL)
 *   admin_post_tropikal_connect_callback   -> complete OAuth (code exchange +
 *                                             registration), then back to Settings
 *
 * `connect` is admin-gated (capability + WordPress nonce). `callback` is not —
 * it is protected by the single-use hashed OAuth state and the exact redirect
 * URI match, exactly as the authorization-code flow requires.
 */
final readonly class ConnectController
{
    public function __construct(
        private PermissionGate $gate,
        private ConnectFlow $flow,
        private Notices $notices,
    ) {
    }

    public function beginConnect(): void
    {
        if (! $this->gate->currentUserCanManage()) {
            wp_die(esc_html__('You do not have permission to manage TROPIKAL Connect.', 'tropikal-connect-wordpress'));
        }
        check_admin_referer('tropikal_connect_connect');

        try {
            $url = $this->flow->begin((string) get_current_user_id());
        } catch (\Throwable $exception) {
            $this->notices->add('error', $exception->getMessage());
            $this->redirectToSettings();

            return;
        }

        wp_redirect($url);
        exit;
    }

    public function handleCallback(): void
    {
        $state = sanitize_text_field((string) ($_GET['state'] ?? ''));
        $code = sanitize_text_field((string) ($_GET['code'] ?? ''));

        try {
            $this->flow->complete($state, $code, $this->currentUrl());
            $this->notices->add('success', __('Connected to TROPIKAL.', 'tropikal-connect-wordpress'));
        } catch (\Throwable $exception) {
            $this->notices->add('error', $exception->getMessage());
        }

        $this->redirectToSettings();
    }

    private function currentUrl(): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

        return $host !== '' ? $scheme . '://' . $host . $uri : admin_url('admin-post.php?action=tropikal_connect_callback');
    }

    private function redirectToSettings(): void
    {
        wp_safe_redirect(admin_url('options-general.php?page=tropikal-connect'));
        exit;
    }
}
