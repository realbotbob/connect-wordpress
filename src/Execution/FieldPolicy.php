<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Execution;

use Tropikal\Connect\WordPress\Dto\BusinessObjectDescriptor;
use Tropikal\Connect\WordPress\Exception\ValidationException;
use TropikalAI\Connect\Domain\Security\SensitiveData;

final class FieldPolicy
{
    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    public function project(BusinessObjectDescriptor $object, array $record): array
    {
        $projected = [];
        foreach ($object->readableFields() as $field) {
            if (SensitiveData::isSensitiveKey($field)) {
                continue;
            }
            if (array_key_exists($field, $record)) {
                $projected[$field] = $record[$field];
            }
        }

        return $projected;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function validateWrite(BusinessObjectDescriptor $object, array $payload): array
    {
        $allowed = $object->writableFields();
        $safe = [];

        foreach ($payload as $field => $value) {
            if (in_array($field, ['id', 'type', 'approval_id', 'approved_by', 'approved_at'], true)) {
                continue;
            }
            if (SensitiveData::isSensitiveKey((string) $field) || ! in_array((string) $field, $allowed, true)) {
                throw new ValidationException("Field is not writable: {$field}");
            }

            $safe[(string) $field] = $value;
        }

        return $safe;
    }
}
