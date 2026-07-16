# Changelog

## 0.1.0 - Unreleased

- Initial TROPIKAL Connect WordPress plugin implementation.
- One-click connect via OAuth 2.1 authorization-code flow with PKCE (`S256`),
  dynamic client registration, control-plane installation registration, and
  encrypted refresh-token storage for sync. Fails closed — the connection is
  stored only when the control plane returns a server signing key; no
  locally-generated secret fallback.
- Four independent, strictly-additive grants per business object (Read, Create,
  Update, Delete), replacing the earlier Read/Write/Delete model.
- Registered public custom fields (`show_in_rest` post meta) discovered and
  exposed as writable `meta.<key>` fields for read and update.
- Cross-language signed-request contract vector test pinning the canonical
  signing scheme to the shared fixture.

