<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Security;

final class PermissionGate
{
    public const CAPABILITY = 'tropikal_connect_manage';

    public function currentUserCanManage(): bool
    {
        return current_user_can(self::CAPABILITY) || current_user_can('manage_options');
    }

    public function installCapability(): void
    {
        $role = get_role('administrator');
        if ($role && method_exists($role, 'add_cap')) {
            $role->add_cap(self::CAPABILITY);
        }
    }
}
