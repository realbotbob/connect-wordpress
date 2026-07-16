<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Execution;

use Throwable;
use Tropikal\Connect\WordPress\Discovery\BusinessObjectDiscoveryService;
use Tropikal\Connect\WordPress\Dto\BridgeRequest;
use Tropikal\Connect\WordPress\Dto\BridgeResponse;
use Tropikal\Connect\WordPress\Dto\BusinessObjectDescriptor;
use Tropikal\Connect\WordPress\Exception\ConnectWordPressException;
use Tropikal\Connect\WordPress\Exception\GrantDeniedException;
use Tropikal\Connect\WordPress\Exception\UnsupportedOperationException;
use Tropikal\Connect\WordPress\Exception\ValidationException;
use Tropikal\Connect\WordPress\Storage\AuditLogRepository;
use Tropikal\Connect\WordPress\Storage\ConnectionRepository;
use Tropikal\Connect\WordPress\Storage\GrantRepository;

final readonly class BridgeExecutor
{
    public function __construct(
        private ConnectionRepository $connections,
        private GrantRepository $grants,
        private BusinessObjectDiscoveryService $discovery,
        private FieldPolicy $fields,
        private ContentSearcher $searcher,
        private ContentReader $reader,
        private ContentWriter $writer,
        private ApprovedPublisher $publisher,
        private ContentDeleter $deleter,
        private MediaReader $mediaReader,
        private MediaWriter $mediaWriter,
        private AuditLogRepository $audit,
    ) {
    }

    public function execute(BridgeRequest $request): BridgeResponse
    {
        if (! $this->connections->isConnected()) {
            return BridgeResponse::error('connection_missing', 'TROPIKAL Connect is not connected.', 403);
        }

        $objects = $this->discovery->discover();
        $object = $objects[$request->resourceKey] ?? null;
        if (! $object) {
            return BridgeResponse::error('resource_not_found', 'Connected Data resource was not found.', 404);
        }

        try {
            $this->assertAllowed($object, $request->operation);
            $response = $this->executeAllowed($object, $request);
            $this->connections->markBridgeCall();
            $this->auditMutation($request, $response->statusCode < 400 ? 'success' : 'error');

            return $response;
        } catch (ConnectWordPressException $exception) {
            $this->auditMutation($request, 'error', $exception->errorCode);

            return BridgeResponse::error($exception->errorCode, $exception->getMessage(), $exception->statusCode);
        } catch (Throwable) {
            $this->auditMutation($request, 'error', 'wordpress_error');

            return BridgeResponse::error('wordpress_error', 'WordPress operation failed.', 500);
        }
    }

    private function executeAllowed(BusinessObjectDescriptor $object, BridgeRequest $request): BridgeResponse
    {
        if ($object->kind === 'media') {
            return $this->executeMedia($object, $request);
        }

        return match ($request->operation) {
            'wordpress.resource.list', 'wordpress.resource.search' => BridgeResponse::ok($this->searcher->search($object, $request->payload)),
            'wordpress.resource.get' => $this->get($object, $request),
            'wordpress.resource.create', 'wordpress.resource.draft_write' => $this->create($object, $request),
            'wordpress.resource.update' => $this->update($object, $request),
            'wordpress.resource.publish_approved' => $this->publish($object, $request),
            'wordpress.resource.delete' => $this->delete($object, $request),
            default => throw new UnsupportedOperationException(),
        };
    }

    private function executeMedia(BusinessObjectDescriptor $object, BridgeRequest $request): BridgeResponse
    {
        unset($object);

        return match ($request->operation) {
            'wordpress.resource.list', 'wordpress.resource.search' => BridgeResponse::ok($this->searcher->search($this->discovery->discover()['attachment'], $request->payload)),
            'wordpress.resource.get', 'wordpress.media.get' => $this->mediaGet($request),
            'wordpress.media.upload' => BridgeResponse::created($this->mediaWriter->upload($request->payload)),
            'wordpress.resource.delete', 'wordpress.media.delete' => $this->delete($this->discovery->discover()['attachment'], $request),
            default => throw new UnsupportedOperationException(),
        };
    }

    private function get(BusinessObjectDescriptor $object, BridgeRequest $request): BridgeResponse
    {
        $id = $this->requiredId($request->payload);
        $record = $this->reader->get($object, $id);
        if ($record === null) {
            return BridgeResponse::error('resource_not_found', 'Content record was not found.', 404);
        }

        return BridgeResponse::ok($this->fields->project($object, $record));
    }

    private function mediaGet(BridgeRequest $request): BridgeResponse
    {
        $id = $this->requiredId($request->payload);
        $record = $this->mediaReader->get($id);
        if ($record === null) {
            return BridgeResponse::error('resource_not_found', 'Media record was not found.', 404);
        }

        return BridgeResponse::ok($record);
    }

    private function create(BusinessObjectDescriptor $object, BridgeRequest $request): BridgeResponse
    {
        $payload = $this->fields->validateWrite($object, $request->payload);

        return BridgeResponse::created($this->fields->project($object, $this->writer->create($object, $payload)));
    }

    private function update(BusinessObjectDescriptor $object, BridgeRequest $request): BridgeResponse
    {
        $id = $this->requiredId($request->payload);
        $payload = $request->payload;
        unset($payload['id']);
        $payload = $this->fields->validateWrite($object, $payload);

        return BridgeResponse::ok($this->fields->project($object, $this->writer->update($object, $id, $payload)));
    }

    private function publish(BusinessObjectDescriptor $object, BridgeRequest $request): BridgeResponse
    {
        $record = $this->publisher->publish($object, $request->payload);

        return BridgeResponse::ok($this->fields->project($object, $record));
    }

    private function delete(BusinessObjectDescriptor $object, BridgeRequest $request): BridgeResponse
    {
        $id = $this->requiredId($request->payload);
        if (! $this->deleter->delete($object, $id)) {
            throw new ValidationException('Content record could not be moved to trash.');
        }

        return BridgeResponse::deleted($id);
    }

    private function assertAllowed(BusinessObjectDescriptor $object, string $operation): void
    {
        $grant = match ($operation) {
            'wordpress.resource.list', 'wordpress.resource.search', 'wordpress.resource.get', 'wordpress.media.get' => 'read',
            'wordpress.resource.create', 'wordpress.resource.draft_write', 'wordpress.media.upload' => 'create',
            'wordpress.resource.update', 'wordpress.resource.publish_approved' => 'update',
            'wordpress.resource.delete', 'wordpress.media.delete' => 'delete',
            default => throw new UnsupportedOperationException(),
        };

        if (! $this->supports($object, $operation)) {
            throw new UnsupportedOperationException('Connected Data resource does not support this operation.');
        }
        if (! $this->grants->allows($object->key, $grant)) {
            throw new GrantDeniedException();
        }
    }

    private function supports(BusinessObjectDescriptor $object, string $operation): bool
    {
        $local = match ($operation) {
            'wordpress.resource.list' => 'list',
            'wordpress.resource.search' => 'search',
            'wordpress.resource.get', 'wordpress.media.get' => 'get',
            'wordpress.resource.create' => 'create',
            'wordpress.resource.update' => 'update',
            'wordpress.resource.draft_write' => 'draft_write',
            'wordpress.resource.publish_approved' => 'publish_approved',
            'wordpress.resource.delete', 'wordpress.media.delete' => 'delete',
            'wordpress.media.upload' => 'upload',
            default => '',
        };

        return in_array($local, $object->operations, true);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requiredId(array $payload): int
    {
        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            throw new ValidationException('Operation requires a valid id.');
        }

        return $id;
    }

    private function auditMutation(BridgeRequest $request, string $status, ?string $errorCode = null): void
    {
        if (
            ! in_array($request->operation, [
            'wordpress.resource.create',
            'wordpress.resource.update',
            'wordpress.resource.draft_write',
            'wordpress.resource.publish_approved',
            'wordpress.resource.delete',
            'wordpress.media.upload',
            'wordpress.media.delete',
            ], true)
        ) {
            return;
        }

        $this->audit->record(
            eventType: 'bridge_call',
            status: $status,
            resourceKey: $request->resourceKey,
            operation: $request->operation,
            targetId: isset($request->payload['id']) ? (string) $request->payload['id'] : null,
            correlationId: $request->correlationId,
            errorCode: $errorCode,
            metadata: ['payload' => $request->payload],
        );
    }
}
