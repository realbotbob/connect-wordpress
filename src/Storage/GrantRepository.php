<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Storage;

use TropikalAI\Connect\Domain\Security\SensitiveData;

final readonly class GrantRepository
{
    private const OPTION = 'enabled_grants';

    private const GRANTS = ['read', 'create', 'update', 'delete'];

    public function __construct(private OptionsRepository $options)
    {
    }

    /**
     * @return array<string, list<string>>
     */
    public function all(): array
    {
        $stored = $this->options->array(self::OPTION);
        $grants = [];

        foreach ($stored as $resourceKey => $resourceGrants) {
            if (! is_string($resourceKey) || ! is_array($resourceGrants) || SensitiveData::isSensitiveKey($resourceKey)) {
                continue;
            }

            $safe = array_values(array_intersect(array_map('strval', $resourceGrants), self::GRANTS));
            if ($safe !== []) {
                $grants[$resourceKey] = $safe;
            }
        }

        return $grants;
    }

    public function allows(string $resourceKey, string $grant): bool
    {
        return in_array($grant, $this->all()[$resourceKey] ?? [], true);
    }

    public function set(string $resourceKey, string $grant, bool $enabled): void
    {
        if (! in_array($grant, self::GRANTS, true)) {
            throw new \InvalidArgumentException('Grant must be read, create, update, or delete.');
        }

        SensitiveData::assertPublicKey($resourceKey);

        $grants = $this->all();
        $resourceGrants = $grants[$resourceKey] ?? [];
        if ($enabled) {
            $resourceGrants[] = $grant;
        } else {
            $resourceGrants = array_values(array_diff($resourceGrants, [$grant]));
        }

        if ($resourceGrants === []) {
            unset($grants[$resourceKey]);
        } else {
            $grants[$resourceKey] = array_values(array_unique($resourceGrants));
        }

        $this->options->set(self::OPTION, $grants);
    }

    /**
     * @param array<string, list<string>> $grants
     */
    public function replace(array $grants): void
    {
        $safe = [];
        foreach ($grants as $resourceKey => $resourceGrants) {
            SensitiveData::assertPublicKey((string) $resourceKey);
            $allowed = array_values(array_intersect($resourceGrants, self::GRANTS));
            if ($allowed !== []) {
                $safe[(string) $resourceKey] = $allowed;
            }
        }

        $this->options->set(self::OPTION, $safe);
    }
}
