<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress;

use Tropikal\Connect\WordPress\Admin\AdminActionController;
use Tropikal\Connect\WordPress\Admin\AdminPage;
use Tropikal\Connect\WordPress\Admin\Notices;
use Tropikal\Connect\WordPress\Discovery\BusinessObjectDiscoveryService;
use Tropikal\Connect\WordPress\Discovery\ExtensionDetector;
use Tropikal\Connect\WordPress\Discovery\WordPressSchemaMapper;
use Tropikal\Connect\WordPress\Execution\ApprovedPublisher;
use Tropikal\Connect\WordPress\Execution\BridgeExecutor;
use Tropikal\Connect\WordPress\Execution\ContentDeleter;
use Tropikal\Connect\WordPress\Execution\ContentReader;
use Tropikal\Connect\WordPress\Execution\ContentSearcher;
use Tropikal\Connect\WordPress\Execution\ContentWriter;
use Tropikal\Connect\WordPress\Execution\FieldPolicy;
use Tropikal\Connect\WordPress\Execution\MediaReader;
use Tropikal\Connect\WordPress\Execution\MediaWriter;
use Tropikal\Connect\WordPress\Rest\BridgeController;
use Tropikal\Connect\WordPress\Rest\HealthController;
use Tropikal\Connect\WordPress\Rest\IdentityController;
use Tropikal\Connect\WordPress\Rest\ManifestController;
use Tropikal\Connect\WordPress\Rest\Routes;
use Tropikal\Connect\WordPress\Security\NonceStore;
use Tropikal\Connect\WordPress\Security\PermissionGate;
use Tropikal\Connect\WordPress\Security\SecretStore;
use Tropikal\Connect\WordPress\Security\SignatureVerifier;
use Tropikal\Connect\WordPress\Admin\ConnectController;
use Tropikal\Connect\WordPress\Setup\ConnectConfig;
use Tropikal\Connect\WordPress\Setup\ConnectFlow;
use Tropikal\Connect\WordPress\Setup\PendingAuthorizationStore;
use Tropikal\Connect\WordPress\Setup\WpAuthorizationServerGateway;
use Tropikal\Connect\WordPress\Setup\WpControlPlaneGateway;
use Tropikal\Connect\WordPress\Setup\WpHttp;
use Tropikal\Connect\WordPress\Storage\AuditLogRepository;
use Tropikal\Connect\WordPress\Storage\ConnectionRepository;
use Tropikal\Connect\WordPress\Storage\GrantRepository;
use Tropikal\Connect\WordPress\Storage\OptionsRepository;
use Tropikal\Connect\WordPress\WordPress\SiteIdentityProvider;
use TropikalAI\Connect\Application\SignedRequestVerifier;

final class Plugin
{
    private static ?self $instance = null;

    private OptionsRepository $options;

    private ConnectionRepository $connections;

    private GrantRepository $grants;

    private AuditLogRepository $audit;

    private NonceStore $nonces;

    private PermissionGate $gate;

    private Notices $notices;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
        $this->options = new OptionsRepository();
        $this->connections = new ConnectionRepository($this->options);
        $this->grants = new GrantRepository($this->options);
        $this->audit = new AuditLogRepository();
        $this->nonces = new NonceStore();
        $this->gate = new PermissionGate();
        $this->notices = new Notices();
    }

    public function boot(): void
    {
        $services = $this->services();

        add_action('admin_menu', [$services['admin_page'], 'register']);
        add_action('admin_post_tropikal_connect_action', [$services['admin_actions'], 'handle']);
        add_action('admin_post_tropikal_connect_connect', [$services['connect'], 'beginConnect']);
        add_action('admin_post_tropikal_connect_callback', [$services['connect'], 'handleCallback']);
        add_action('admin_notices', [$this->notices, 'render']);
        add_action('rest_api_init', [$services['routes'], 'register']);
        add_action('tropikal_connect_cleanup_nonces', [$this->nonces, 'cleanup']);
    }

    public function activate(): void
    {
        $this->gate->installCapability();
        $this->nonces->install();
        $this->audit->install();
        if (! wp_next_scheduled('tropikal_connect_cleanup_nonces')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'tropikal_connect_cleanup_nonces');
        }
    }

    public function deactivate(): void
    {
        wp_clear_scheduled_hook('tropikal_connect_cleanup_nonces');
    }

    /**
     * @return array<string, object>
     */
    private function services(): array
    {
        $identity = new SiteIdentityProvider();
        $discovery = new BusinessObjectDiscoveryService();
        $schema = new WordPressSchemaMapper($discovery, $this->grants, new ExtensionDetector());
        $secrets = new SecretStore();
        $config = ConnectConfig::fromWordPress();
        $http = new WpHttp($config->timeoutSeconds);
        $connectFlow = new ConnectFlow(
            $config,
            new PendingAuthorizationStore($this->options, $secrets),
            new WpAuthorizationServerGateway($config, $http),
            new WpControlPlaneGateway($config, $http),
            $this->connections,
            $secrets,
            $this->grants,
            $schema,
            $identity,
            $this->audit,
        );
        $reader = new ContentReader();
        $searcher = new ContentSearcher($reader);
        $writer = new ContentWriter($reader);
        $bridgeExecutor = new BridgeExecutor(
            $this->connections,
            $this->grants,
            $discovery,
            new FieldPolicy(),
            $searcher,
            $reader,
            $writer,
            new ApprovedPublisher($reader),
            new ContentDeleter(),
            new MediaReader(),
            new MediaWriter(),
            $this->audit,
        );
        $signatureVerifier = new SignatureVerifier(
            $this->connections,
            $secrets,
            new SignedRequestVerifier($this->nonces),
        );

        $routes = new Routes(
            new HealthController($this->connections),
            new IdentityController($identity),
            new ManifestController($schema, $identity),
            new BridgeController($signatureVerifier, $bridgeExecutor, $this->audit),
        );

        return [
            'admin_page' => new AdminPage($this->gate, $this->notices, $this->connections, $this->grants, $discovery, $identity, $secrets, $this->audit),
            'admin_actions' => new AdminActionController($this->gate, $this->notices, $this->grants, $discovery, $connectFlow, $this->audit),
            'routes' => $routes,
            'connect' => new ConnectController($this->gate, $connectFlow, $this->notices),
        ];
    }
}
