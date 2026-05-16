<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Storage;

final class OptionsRepository
{
    private const PREFIX = 'tropikal_connect_';

    public function get(string $key, mixed $default = null): mixed
    {
        return get_option(self::PREFIX . $key, $default);
    }

    public function string(string $key): ?string
    {
        $value = $this->get($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @return array<mixed>
     */
    public function array(string $key): array
    {
        $value = $this->get($key, []);

        return is_array($value) ? $value : [];
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key, false);
    }

    public function set(string $key, mixed $value): void
    {
        update_option(self::PREFIX . $key, $value, false);
    }

    public function delete(string $key): void
    {
        delete_option(self::PREFIX . $key);
    }
}
