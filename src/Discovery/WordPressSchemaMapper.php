<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Discovery;

use Tropikal\Connect\WordPress\Dto\BusinessObjectDescriptor;
use Tropikal\Connect\WordPress\Dto\ResourceManifest;
use Tropikal\Connect\WordPress\Dto\SiteIdentity;
use Tropikal\Connect\WordPress\Storage\GrantRepository;
use TropikalAI\Connect\Domain\Capabilities\CapabilityDescriptor;
use TropikalAI\Connect\Domain\Capabilities\CapabilitySet;
use TropikalAI\Connect\Domain\Capabilities\FieldDescriptor as CoreFieldDescriptor;
use TropikalAI\Connect\Domain\Capabilities\OperationDescriptor;

final readonly class WordPressSchemaMapper
{
    public function __construct(
        private BusinessObjectDiscoveryService $discovery,
        private GrantRepository $grants,
        private ExtensionDetector $extensions,
    ) {
    }

    public function manifest(SiteIdentity $identity): ResourceManifest
    {
        $resources = [];
        foreach ($this->discovery->discover() as $key => $object) {
            $resourceGrants = $this->grants->all()[$key] ?? [];
            if ($resourceGrants === []) {
                continue;
            }

            $resources[] = $this->resource($object, $resourceGrants);
        }

        return new ResourceManifest($identity, $resources, [
            'extensions' => $this->extensions->detect(),
        ]);
    }

    /**
     * @param list<string> $grants
     * @return array<string, mixed>
     */
    private function resource(BusinessObjectDescriptor $object, array $grants): array
    {
        $operations = $this->operations($object, $grants);
        $fields = [];
        foreach ($object->fields as $field) {
            $fields[$field->key] = new CoreFieldDescriptor(
                name: $field->key,
                type: $field->type,
                readable: $field->readable,
                writable: $field->writable,
                required: $field->required,
            );
        }

        $capability = new CapabilityDescriptor(
            sourceKind: 'connect_wordpress',
            resourceKey: $object->key,
            resourceLabel: $object->label,
            fields: $fields,
            operations: $operations,
            grants: $grants,
            metadata: ['kind' => $object->kind],
        );

        $payload = (new CapabilitySet([$capability]))->publicPayload()[0];
        $payload['label'] = $object->label;
        $payload['access'] = [
            'read' => in_array('read', $grants, true),
            'write' => in_array('write', $grants, true),
            'delete' => in_array('delete', $grants, true),
        ];
        $payload['readable_fields'] = $object->readableFields();
        $payload['writable_fields'] = $object->writableFields();
        $payload['risk_level'] = in_array('write', $grants, true) || in_array('delete', $grants, true)
            ? 'writes_require_confirmation'
            : 'read';

        return $payload;
    }

    /**
     * @param list<string> $grants
     * @return list<OperationDescriptor>
     */
    private function operations(BusinessObjectDescriptor $object, array $grants): array
    {
        $operations = [];
        if (in_array('read', $grants, true)) {
            $operations[] = new OperationDescriptor("{$object->key}.list", 'wordpress.resource.list', 'read', $this->listSchema(), ['type' => 'object']);
            $operations[] = new OperationDescriptor("{$object->key}.search", 'wordpress.resource.search', 'read', $this->listSchema(), ['type' => 'object']);
            $operations[] = new OperationDescriptor("{$object->key}.get", 'wordpress.resource.get', 'read', [
                'type' => 'object',
                'required' => ['id'],
                'additionalProperties' => false,
                'properties' => ['id' => ['type' => 'integer']],
            ], ['type' => 'object']);
        }

        if (in_array('write', $grants, true)) {
            $writeSchema = $this->writeSchema($object, false);
            $operations[] = new OperationDescriptor("{$object->key}.create", 'wordpress.resource.create', 'write', $this->writeSchema($object, true), ['type' => 'object'], true);
            $operations[] = new OperationDescriptor("{$object->key}.update", 'wordpress.resource.update', 'write', [
                ...$writeSchema,
                'required' => ['id'],
                'properties' => ['id' => ['type' => 'integer'], ...$writeSchema['properties']],
            ], ['type' => 'object'], true);
            $operations[] = new OperationDescriptor("{$object->key}.publish_approved", 'wordpress.resource.publish_approved', 'write', [
                'type' => 'object',
                'required' => ['id', 'approval_id', 'approved_by', 'approved_at'],
                'additionalProperties' => false,
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'approval_id' => ['type' => 'string'],
                    'approved_by' => ['type' => 'string'],
                    'approved_at' => ['type' => 'string'],
                ],
            ], ['type' => 'object'], true);
        }

        if (in_array('delete', $grants, true)) {
            $operations[] = new OperationDescriptor("{$object->key}.delete", 'wordpress.resource.delete', 'destructive', [
                'type' => 'object',
                'required' => ['id'],
                'additionalProperties' => false,
                'properties' => ['id' => ['type' => 'integer']],
            ], ['type' => 'object'], true);
        }

        return $operations;
    }

    /**
     * @return array<string, mixed>
     */
    private function listSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'query' => ['type' => 'string'],
                'search' => ['type' => 'string'],
                'status' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                'page' => ['type' => 'integer', 'minimum' => 1],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function writeSchema(BusinessObjectDescriptor $object, bool $creating): array
    {
        $properties = [];
        $required = [];
        foreach ($object->fields as $field) {
            if (! $field->writable) {
                continue;
            }
            $properties[$field->key] = $this->jsonSchemaType($field->type);
            if ($creating && $field->required) {
                $required[] = $field->key;
            }
        }

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => $properties,
        ];
        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonSchemaType(string $type): array
    {
        return match ($type) {
            'integer' => ['type' => 'integer'],
            'boolean' => ['type' => 'boolean'],
            'datetime' => ['type' => 'string', 'format' => 'date-time'],
            'url' => ['type' => 'string', 'format' => 'uri'],
            'object' => ['type' => 'object'],
            default => ['type' => 'string'],
        };
    }
}
