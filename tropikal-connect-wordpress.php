<?php

/**
 * Plugin Name: TROPIKAL Connect for WordPress
 * Description: Connect approved WordPress business objects to TROPIKAL Connect.
 * Version: 0.1.0
 * Requires PHP: 8.2
 * Author: TROPIKAL AI
 * License: MIT
 * Text Domain: tropikal-connect-wordpress
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('TROPIKAL_CONNECT_WORDPRESS_VERSION', '0.1.0');
define('TROPIKAL_CONNECT_WORDPRESS_FILE', __FILE__);
define('TROPIKAL_CONNECT_WORDPRESS_DIR', plugin_dir_path(__FILE__));
define('TROPIKAL_CONNECT_WORDPRESS_URL', plugin_dir_url(__FILE__));

$tropikalConnectAutoload = TROPIKAL_CONNECT_WORDPRESS_DIR . 'vendor/autoload.php';

if (is_readable($tropikalConnectAutoload)) {
    require_once $tropikalConnectAutoload;

    $tropikalConnectPlugin = \Tropikal\Connect\WordPress\Plugin::instance();

    register_activation_hook(__FILE__, [$tropikalConnectPlugin, 'activate']);
    register_deactivation_hook(__FILE__, [$tropikalConnectPlugin, 'deactivate']);

    add_action('plugins_loaded', [$tropikalConnectPlugin, 'boot']);
} else {
    add_action('admin_notices', static function (): void {
        if (! current_user_can('activate_plugins')) {
            return;
        }
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('TROPIKAL Connect for WordPress requires Composer dependencies. Run composer install or install a release ZIP.', 'tropikal-connect-wordpress');
        echo '</p></div>';
    });
}
