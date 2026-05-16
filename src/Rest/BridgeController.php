<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Rest;

use Tropikal\Connect\WordPress\Dto\BridgeRequest;
use Tropikal\Connect\WordPress\Dto\BridgeResponse;
use Tropikal\Connect\WordPress\Execution\BridgeExecutor;
use Tropikal\Connect\WordPress\Security\SignatureVerifier;
use Tropikal\Connect\WordPress\Storage\AuditLogRepository;

final readonly class BridgeController
{
    public function __construct(
        private SignatureVerifier $signatures,
        private BridgeExecutor $executor,
        private AuditLogRepository $audit,
    ) {
    }

    public function __invoke(\WP_REST_Request $request): \WP_REST_Response
    {
        $body = $request->get_body();
        $headers = $request->get_headers();
        $path = '/' . ltrim((string) wp_parse_url((string) $request->get_route(), PHP_URL_PATH), '/');
        $query = $request->get_query_params();

        try {
            $this->signatures->verify(
                method: (string) $request->get_method(),
                path: 'wp-json/' . ltrim((string) $request->get_route(), '/'),
                query: $query,
                body: $body,
                headers: $headers,
            );
        } catch (\Throwable $exception) {
            $this->audit->record('failed_signature', 'error', errorCode: 'invalid_signature', metadata: [
                'route' => $path,
                'message' => $exception->getMessage(),
            ]);

            return new \WP_REST_Response(BridgeResponse::error('invalid_signature', 'Signed request could not be verified.', 401)->payload, 401);
        }

        $data = json_decode($body, true);
        if (! is_array($data)) {
            return new \WP_REST_Response(BridgeResponse::error('validation_error', 'Bridge request body must be JSON.', 422)->payload, 422);
        }

        $response = $this->executor->execute(BridgeRequest::fromArray($data));

        return new \WP_REST_Response($response->payload, $response->statusCode);
    }
}
