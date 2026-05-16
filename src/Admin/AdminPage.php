<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Admin;

use Tropikal\Connect\WordPress\Discovery\BusinessObjectDiscoveryService;
use Tropikal\Connect\WordPress\Security\PermissionGate;
use Tropikal\Connect\WordPress\Security\SecretStore;
use Tropikal\Connect\WordPress\Storage\AuditLogRepository;
use Tropikal\Connect\WordPress\Storage\ConnectionRepository;
use Tropikal\Connect\WordPress\Storage\GrantRepository;
use Tropikal\Connect\WordPress\WordPress\SiteIdentityProvider;

final readonly class AdminPage
{
    public function __construct(
        private PermissionGate $gate,
        private Notices $notices,
        private ConnectionRepository $connections,
        private GrantRepository $grants,
        private BusinessObjectDiscoveryService $discovery,
        private SiteIdentityProvider $identity,
        private SecretStore $secrets,
        private AuditLogRepository $audit,
    ) {
    }

    public function register(): void
    {
        add_options_page(
            __('TROPIKAL Connect', 'tropikal-connect-wordpress'),
            __('TROPIKAL Connect', 'tropikal-connect-wordpress'),
            PermissionGate::CAPABILITY,
            'tropikal-connect',
            [$this, 'render'],
        );
    }

    public function render(): void
    {
        if (! $this->gate->currentUserCanManage()) {
            wp_die(esc_html__('You do not have permission to manage TROPIKAL Connect.', 'tropikal-connect-wordpress'));
        }

        $identity = $this->identity->identity();
        $status = $this->connections->publicStatus();
        $grants = $this->grants->all();
        $objects = $this->discovery->discover();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('TROPIKAL Connect', 'tropikal-connect-wordpress'); ?></h1>
            <?php $this->notices->render(); ?>

            <?php if (! $this->secrets->available()) : ?>
                <div class="notice notice-error"><p><?php echo esc_html__('Configure TROPIKAL_CONNECT_ENCRYPTION_KEY or WordPress salts before connecting.', 'tropikal-connect-wordpress'); ?></p></div>
            <?php endif; ?>

            <h2><?php echo esc_html__('Connection', 'tropikal-connect-wordpress'); ?></h2>
            <table class="widefat striped" style="max-width: 900px;">
                <tbody>
                    <tr><th><?php echo esc_html__('Site', 'tropikal-connect-wordpress'); ?></th><td><?php echo esc_html($identity->name); ?></td></tr>
                    <tr><th><?php echo esc_html__('Status', 'tropikal-connect-wordpress'); ?></th><td><?php echo $status['connected'] ? esc_html__('Connected', 'tropikal-connect-wordpress') : esc_html__('Not connected', 'tropikal-connect-wordpress'); ?></td></tr>
                    <tr><th><?php echo esc_html__('Installation', 'tropikal-connect-wordpress'); ?></th><td><?php echo esc_html((string) ($status['installation_id'] ?? '')); ?></td></tr>
                    <tr><th><?php echo esc_html__('Last sync', 'tropikal-connect-wordpress'); ?></th><td><?php echo esc_html((string) ($status['last_sync_at'] ?? '')); ?></td></tr>
                    <tr><th><?php echo esc_html__('Last bridge call', 'tropikal-connect-wordpress'); ?></th><td><?php echo esc_html((string) ($status['last_bridge_call_at'] ?? '')); ?></td></tr>
                </tbody>
            </table>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin: 16px 0;">
                <?php wp_nonce_field('tropikal_connect_action'); ?>
                <input type="hidden" name="action" value="tropikal_connect_action">
                <button class="button button-primary" name="tropikal_connect_action" value="connect"><?php echo esc_html__('Connect', 'tropikal-connect-wordpress'); ?></button>
                <button class="button" name="tropikal_connect_action" value="sync"><?php echo esc_html__('Sync Connected Data', 'tropikal-connect-wordpress'); ?></button>
                <button class="button" name="tropikal_connect_action" value="rotate"><?php echo esc_html__('Rotate Key', 'tropikal-connect-wordpress'); ?></button>
                <button class="button" name="tropikal_connect_action" value="disconnect"><?php echo esc_html__('Disconnect', 'tropikal-connect-wordpress'); ?></button>
            </form>

            <h2><?php echo esc_html__('Connected Data', 'tropikal-connect-wordpress'); ?></h2>
            <p><?php echo esc_html__('Default access is none. Enable Read, Write, or Delete only for WordPress objects TROPIKAL may use.', 'tropikal-connect-wordpress'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('tropikal_connect_action'); ?>
                <input type="hidden" name="action" value="tropikal_connect_action">
                <input type="hidden" name="tropikal_connect_action" value="save_grants">
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Business Object', 'tropikal-connect-wordpress'); ?></th>
                            <th><?php echo esc_html__('Type', 'tropikal-connect-wordpress'); ?></th>
                            <th><?php echo esc_html__('Readable fields', 'tropikal-connect-wordpress'); ?></th>
                            <th><?php echo esc_html__('Writable fields', 'tropikal-connect-wordpress'); ?></th>
                            <th><?php echo esc_html__('Read', 'tropikal-connect-wordpress'); ?></th>
                            <th><?php echo esc_html__('Write', 'tropikal-connect-wordpress'); ?></th>
                            <th><?php echo esc_html__('Delete', 'tropikal-connect-wordpress'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($objects as $object) : ?>
                        <?php $resourceGrants = $grants[$object->key] ?? []; ?>
                        <tr>
                            <td><strong><?php echo esc_html($object->label); ?></strong><br><code><?php echo esc_html($object->key); ?></code></td>
                            <td><?php echo esc_html($object->kind); ?></td>
                            <td><?php echo esc_html((string) count($object->readableFields())); ?></td>
                            <td><?php echo esc_html((string) count($object->writableFields())); ?></td>
                            <?php foreach (['read', 'write', 'delete'] as $grant) : ?>
                                <td>
                                    <label>
                                        <input type="checkbox" name="grants[<?php echo esc_attr($object->key); ?>][<?php echo esc_attr($grant); ?>]" value="1" <?php checked(in_array($grant, $resourceGrants, true)); ?>>
                                        <?php echo esc_html(ucfirst($grant)); ?>
                                    </label>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p><button class="button button-primary"><?php echo esc_html__('Save Connected Data Access', 'tropikal-connect-wordpress'); ?></button></p>
            </form>

            <h2><?php echo esc_html__('Recent Activity', 'tropikal-connect-wordpress'); ?></h2>
            <table class="widefat striped" style="max-width: 900px;">
                <thead><tr><th><?php echo esc_html__('Time', 'tropikal-connect-wordpress'); ?></th><th><?php echo esc_html__('Event', 'tropikal-connect-wordpress'); ?></th><th><?php echo esc_html__('Status', 'tropikal-connect-wordpress'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($this->audit->recent() as $row) : ?>
                    <tr>
                        <td><?php echo esc_html((string) ($row['created_at'] ?? '')); ?></td>
                        <td><?php echo esc_html((string) ($row['event_type'] ?? '')); ?></td>
                        <td><?php echo esc_html((string) ($row['status'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
