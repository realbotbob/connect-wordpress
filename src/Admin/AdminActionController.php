<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Admin;

use Tropikal\Connect\WordPress\Discovery\BusinessObjectDiscoveryService;
use Tropikal\Connect\WordPress\Security\PermissionGate;
use Tropikal\Connect\WordPress\Setup\ConnectFlow;
use Tropikal\Connect\WordPress\Storage\AuditLogRepository;
use Tropikal\Connect\WordPress\Storage\GrantRepository;

final readonly class AdminActionController
{
    public function __construct(
        private PermissionGate $gate,
        private Notices $notices,
        private GrantRepository $grants,
        private BusinessObjectDiscoveryService $discovery,
        private ConnectFlow $flow,
        private AuditLogRepository $audit,
    ) {
    }

    public function handle(): void
    {
        if (! $this->gate->currentUserCanManage()) {
            wp_die(esc_html__('You do not have permission to manage TROPIKAL Connect.', 'tropikal-connect-wordpress'));
        }

        check_admin_referer('tropikal_connect_action');

        $action = sanitize_key((string) ($_POST['tropikal_connect_action'] ?? ''));
        try {
            match ($action) {
                'save_grants' => $this->saveGrants(),
                'sync' => $this->flow->sync(),
                'disconnect' => $this->flow->disconnect(),
                default => throw new \InvalidArgumentException('Unknown TROPIKAL Connect action.'),
            };

            $this->notices->add('success', __('TROPIKAL Connect settings saved.', 'tropikal-connect-wordpress'));
        } catch (\Throwable $exception) {
            $this->notices->add('error', $exception->getMessage());
        }

        wp_safe_redirect(admin_url('options-general.php?page=tropikal-connect'));
        exit;
    }

    private function saveGrants(): void
    {
        $posted = is_array($_POST['grants'] ?? null) ? wp_unslash($_POST['grants']) : [];
        $objects = $this->discovery->discover();
        $grants = [];

        foreach ($objects as $key => $object) {
            $resource = is_array($posted[$key] ?? null) ? $posted[$key] : [];
            $enabled = [];
            foreach (['read', 'create', 'update', 'delete'] as $grant) {
                if (! empty($resource[$grant])) {
                    $enabled[] = $grant;
                }
            }
            if ($enabled !== []) {
                $grants[$object->key] = $enabled;
            }
        }

        $this->grants->replace($grants);
        $this->audit->record('grant_change', 'success', metadata: ['resources' => array_keys($grants)]);
    }
}
