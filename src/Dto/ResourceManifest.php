<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Dto;

use TropikalAI\Connect\Domain\Security\SensitiveData;

final readonly class ResourceManifest
{
    /**
     * @param list<array<string, mixed>> $resources
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public SiteIdentity $site,
        public array $resources,
        public array $metadata = [],
    ) {
        SensitiveData::assertPublicPayload($resources);
        SensitiveData::assertPublicPayload($metadata);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'integration' => 'wordpress',
            'site' => $this->site->toArray(),
            'resources' => $this->resources,
            'metadata' => $this->metadata,
        ];
    }
}
