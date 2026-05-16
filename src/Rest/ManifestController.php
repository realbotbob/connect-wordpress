<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Rest;

use Tropikal\Connect\WordPress\Discovery\WordPressSchemaMapper;
use Tropikal\Connect\WordPress\WordPress\SiteIdentityProvider;

final readonly class ManifestController
{
    public function __construct(
        private WordPressSchemaMapper $schema,
        private SiteIdentityProvider $identity,
    ) {
    }

    public function __invoke(\WP_REST_Request $request): \WP_REST_Response
    {
        unset($request);

        return new \WP_REST_Response($this->schema->manifest($this->identity->identity())->toArray(), 200);
    }
}
