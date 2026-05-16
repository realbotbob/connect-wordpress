<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Dto;

use TropikalAI\Connect\Domain\Security\SensitiveData;

final readonly class BusinessObjectDescriptor
{
    /**
     * @param list<FieldDescriptor> $fields
     * @param list<string> $operations
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $kind,
        public array $fields,
        public array $operations,
        public bool $readable = true,
        public bool $writable = true,
        public bool $deletable = true,
    ) {
        SensitiveData::assertPublicKey($this->key);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source_kind' => 'connect_wordpress',
            'resource_key' => $this->key,
            'label' => $this->label,
            'kind' => $this->kind,
            'readable' => $this->readable,
            'writable' => $this->writable,
            'deletable' => $this->deletable,
            'fields' => array_map(static fn (FieldDescriptor $field): array => $field->toArray(), $this->fields),
            'operations' => $this->operations,
        ];
    }

    /**
     * @return list<string>
     */
    public function readableFields(): array
    {
        return array_values(array_map(
            static fn (FieldDescriptor $field): string => $field->key,
            array_filter($this->fields, static fn (FieldDescriptor $field): bool => $field->readable),
        ));
    }

    /**
     * @return list<string>
     */
    public function writableFields(): array
    {
        return array_values(array_map(
            static fn (FieldDescriptor $field): string => $field->key,
            array_filter($this->fields, static fn (FieldDescriptor $field): bool => $field->writable),
        ));
    }
}
