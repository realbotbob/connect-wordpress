<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Discovery;

final class ExtensionDetector
{
    /**
     * @return array<string, bool>
     */
    public function detect(): array
    {
        return [
            'woocommerce' => class_exists('WooCommerce'),
            'acf' => function_exists('acf'),
            'yoast_seo' => defined('WPSEO_VERSION'),
            'rank_math' => defined('RANK_MATH_VERSION'),
            'elementor' => defined('ELEMENTOR_VERSION'),
            'wpml' => defined('ICL_SITEPRESS_VERSION'),
            'polylang' => defined('POLYLANG_VERSION'),
        ];
    }
}
