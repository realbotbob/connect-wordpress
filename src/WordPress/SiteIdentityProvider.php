<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\WordPress;

use Tropikal\Connect\WordPress\Dto\SiteIdentity;

final class SiteIdentityProvider
{
    public function identity(): SiteIdentity
    {
        return new SiteIdentity(
            name: (string) get_bloginfo('name'),
            homeUrl: home_url('/'),
            siteUrl: site_url('/'),
            pluginVersion: defined('TROPIKAL_CONNECT_WORDPRESS_VERSION') ? (string) constant('TROPIKAL_CONNECT_WORDPRESS_VERSION') : '0.1.0',
            wordpressVersion: (string) get_bloginfo('version'),
            environment: function_exists('wp_get_environment_type') ? wp_get_environment_type() : null,
            multisite: function_exists('is_multisite') && is_multisite(),
        );
    }
}
