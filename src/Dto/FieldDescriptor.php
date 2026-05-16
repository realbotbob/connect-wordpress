<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Dto;

use TropikalAI\Connect\Domain\Security\SensitiveData;

final readonly class FieldDescriptor
{
    public function __construct(
        public string $key,
        public string $label,
        public string $type = 'string',
        public bool $readable = true,
        public bool $writable = false,
        public bool $required = false,
    ) {
        SensitiveData::assertPublicKey($this->key);
    }

    /**
     * @return array<string, bool|string>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'readable' => $this->readable,
            'writable' => $this->writable,
            'required' => $this->required,
        ];
    }
}
