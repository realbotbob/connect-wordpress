<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Setup;

final readonly class OAuthSetupService
{
    public function __construct(private RegistrationService $registration)
    {
    }

    public function connect(string $adminId): void
    {
        $this->registration->register($adminId);
    }
}
