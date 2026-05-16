<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Dto;

final readonly class SiteIdentity
{
    public function __construct(
        public string $name,
        public string $homeUrl,
        public string $siteUrl,
        public string $pluginVersion,
        public ?string $wordpressVersion = null,
        public ?string $environment = null,
        public bool $multisite = false,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'home_url' => $this->homeUrl,
            'site_url' => $this->siteUrl,
            'integration' => 'wordpress',
            'plugin_version' => $this->pluginVersion,
            'wordpress_version' => $this->wordpressVersion,
            'environment' => $this->environment,
            'multisite' => $this->multisite,
        ];
    }
}
