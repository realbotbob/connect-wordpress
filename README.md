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
- `tropikal-ai/connect` 0.1
- WordPress salts or `TROPIKAL_CONNECT_ENCRYPTION_KEY` for local secret
  encryption

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
3. Click **Connect**.
4. Approve Read, Write, or Delete for each WordPress business object.
5. Click **Sync Connected Data**.

Default access is none. Empty grants expose nothing.

The registration endpoint is intentionally injectable through WordPress filters
so this package does not hardcode deployment-specific URLs:

```php
add_filter('tropikal_connect_wordpress_registration_url', fn () => 'https://example.com/connect/register');
add_filter('tropikal_connect_wordpress_manifest_sync_url', fn () => 'https://example.com/connect/manifest');
```

## Connected Data

The plugin discovers safe WordPress business objects from WordPress APIs:

- Posts
- Pages
- Media
- Public custom post types where safe

Each object has independent grants:

- Read: list, search, and get records
- Write: create drafts, update drafts, and publish with explicit approval
- Delete: move records to trash

Write does not imply Delete.

The V1 safe field set is explicit. It includes title, content, excerpt, status,
slug, featured media, taxonomies, dates, permalink, and safe author display
name. Private post meta, user emails, auth data, sessions, tokens, secrets, and
secret-shaped fields are not exposed by default.

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

Write grant:

- `wordpress.resource.create`
- `wordpress.resource.update`
- `wordpress.resource.draft_write`
- `wordpress.resource.publish_approved`

Delete grant:

- `wordpress.resource.delete`

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
- key rotation
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
- Registration and manifest sync URLs are provided by host configuration or
  filters, not hardcoded in the public package.
