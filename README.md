# TROPIKAL Connect for WordPress

TROPIKAL Connect for WordPress is a native WordPress plugin adapter for
TROPIKAL Connect. It lets a WordPress administrator connect a site, approve
which WordPress business objects TROPIKAL may use, and expose only those
approved operations through signed server-to-server bridge requests.

The plugin does not run an AI agent, Owner Chat, workflow engine, or MCP server
inside WordPress. TROPIKAL backend owns chat, workflows, Functions, and any tool
exposure. This plugin only handles local install, admin authorization,
registration, discovery, grants, signed execution, revocation, replay
protection, and audit logging.

## Requirements

- PHP 8.2 or newer
- WordPress 6.x
- Composer for source installs
- `tropikal-ai/connect` 0.1 (OAuth 2.1 + signed-request primitives)
- `TROPIKAL_CONNECT_ENCRYPTION_KEY` or WordPress salts for encryption at rest

## Installation

### Plugin folder

Clone or extract the plugin into:

```bash
wp-content/plugins/tropikal-connect-wordpress
cd wp-content/plugins/tropikal-connect-wordpress
composer install --no-dev --optimize-autoloader
```

Activate **TROPIKAL Connect for WordPress** in `wp-admin`.

### Composer or Bedrock

```bash
composer require tropikal-ai/connect-wordpress:^0.1
```

Activate the plugin using your normal WordPress deployment flow.

### Release ZIP

```bash
composer install
composer build
```

The distributable ZIP is written to `dist/` and includes production Composer
dependencies. Generated ZIPs are not committed.

## Setup

1. Open `Settings -> TROPIKAL Connect`.
2. Confirm the site identity.
3. Click **Connect to TROPIKAL**. You are redirected to the TROPIKAL
   authorization server to approve the connection, then returned to the
   settings page.
4. Approve Read, Create, Update, or Delete for each WordPress business object.
5. Click **Sync Connected Data** to push the updated capability manifest.

Default access is none. Empty grants expose nothing.

### One-click connect (OAuth 2.1 + PKCE)

**Connect** runs the standard authorization-code flow with PKCE (`S256`), the
same as the Filament and n2n adapters — no secrets are typed into WordPress:

1. If no client is configured, the plugin performs **dynamic client
   registration** against the authorization server.
2. It generates a PKCE verifier and a single-use hashed `state`, persists them
   encrypted, and redirects the admin to the authorization server.
3. On callback it validates `state`, expiry, and the exact redirect URI, then
   **exchanges the code for tokens** (never exposing the verifier).
4. It registers the installation on the TROPIKAL control plane using the access
   token as a Bearer credential and sends the capability manifest.
5. The control plane returns the **server signing key**, which is encrypted at
   rest and used to verify inbound bridge calls.

**Fail-closed:** the connection is stored only when the control plane returns
both a server signing key and an installation ID. There is no
locally-generated secret fallback. The refresh token is stored encrypted so
**Sync** can obtain a fresh access token and re-push the manifest without a new
authorization round-trip.

### Configuration

Defaults target the TROPIKAL production endpoints. Override them with constants
in `wp-config.php` (e.g. to point at a local mock authorization server) —

```php
define('TROPIKAL_CONNECT_AUTH_SERVER_URL', 'https://id.example.test');
define('TROPIKAL_CONNECT_CONTROL_PLANE_URL', 'https://app.example.test');
define('TROPIKAL_CONNECT_SCOPES', 'connect.install');
```

— or programmatically via the `tropikal_connect_wordpress_config` filter, which
receives and returns a `ConnectConfig` value object:

```php
add_filter('tropikal_connect_wordpress_config', function (ConnectConfig $config): ConnectConfig {
    // return a customized ConnectConfig (endpoints, scopes, seeded grants, ...)
    return $config;
});
```

## Connected Data

The plugin discovers safe WordPress business objects from WordPress APIs:

- Posts
- Pages
- Media
- Public custom post types where safe

Each object has four independent grants:

- Read: list, search, and get records
- Create: create drafts and upload media
- Update: update drafts and publish with explicit approval
- Delete: move records to trash

Grants are strictly additive — Create does not imply Update, and neither
implies Delete.

The V1 safe field set is explicit. It includes title, content, excerpt, status,
slug, featured media, taxonomies, dates, permalink, and safe author display
name. Private post meta, user emails, auth data, sessions, tokens, secrets, and
secret-shaped fields are not exposed by default.

### Custom fields

Registered public custom fields (post meta declared with `show_in_rest`) are
discovered automatically and exposed as writable `meta.<key>` fields, so a job
can read and update them exactly like built-in fields. Protected keys (a
leading underscore) and secret-shaped keys are excluded. Register a field with
WordPress core to make it available:

```php
register_post_meta('post', 'subtitle', [
    'type'         => 'string',
    'single'       => true,
    'show_in_rest' => true,
]);
```

## REST Endpoints

Namespace: `tropikal-connect/v1`

- `GET /wp-json/tropikal-connect/v1/health`
- `GET /wp-json/tropikal-connect/v1/identity`
- `GET /wp-json/tropikal-connect/v1/manifest`
- `POST /wp-json/tropikal-connect/v1/bridge`

The bridge endpoint requires a valid TROPIKAL Connect signed request. Health,
identity, and manifest return safe metadata only.

## Signed Bridge Security

Bridge calls are verified using `tropikal-ai/connect` canonical request
primitives. The signature binds:

- HTTP method
- path
- normalized query string
- timestamp
- nonce
- body SHA-256 hash
- installation ID

Requests are rejected when signatures are missing or invalid, timestamps are
stale, body hashes do not match, nonces are replayed, the installation is
revoked, the resource is not granted, or the operation is not supported.

Expected validation errors return structured 400/422 responses. Secrets,
signatures, tokens, private keys, and session data are never returned in REST
responses, admin HTML, JavaScript, manifests, logs, or examples.

## Content Operations

Read grant:

- `wordpress.resource.list`
- `wordpress.resource.search`
- `wordpress.resource.get`

Create grant:

- `wordpress.resource.create`
- `wordpress.resource.draft_write`
- `wordpress.media.upload`

Update grant:

- `wordpress.resource.update`
- `wordpress.resource.publish_approved`

Delete grant:

- `wordpress.resource.delete`
- `wordpress.media.delete`

New content is created as a draft. Updating an existing published post creates
a draft proposal instead of silently overwriting live content. Publishing
requires approval metadata: `approval_id`, `approved_by`, `approved_at`, and the
target content ID. Deletes move content to trash where WordPress supports it.

## Secret Storage

Secrets are encrypted before storage in WordPress options.

Key source order:

1. `TROPIKAL_CONNECT_ENCRYPTION_KEY`
2. WordPress salts

If no strong key material is available, connection setup fails closed and the
admin page shows an actionable notice.

## Audit Logging

The plugin creates audit tables on activation and records:

- connect
- disconnect / revoke
- manifest sync
- grant changes
- bridge calls
- draft writes
- publish-approved actions
- media uploads
- deletes
- failed signatures
- replay attempts
- grant denials

Audit metadata is recursively redacted before storage.

## Multisite

V1 supports per-site connections. Network-level setup is not implemented. In
multisite, options and grants are site-specific by default.

## Local Development

```bash
composer install
composer test
composer lint
composer analyse
```

You can clone the plugin into a local WordPress install and activate it from
`wp-admin`. Use `example.com` or local development URLs in tests and examples.

## Current Limitations

- WooCommerce, ACF, SEO, multilingual, menu, and comment integrations are
  detected only as extension metadata in V1.
- Full live-content proposal review UI is intentionally minimal. Published
  updates create draft proposals instead of modifying live content directly.
- Authorization-server, control-plane, scope, and resource endpoints are
  provided by host configuration (constants or the config filter) and default
  to the TROPIKAL production endpoints; they are not hardcoded per deployment.
