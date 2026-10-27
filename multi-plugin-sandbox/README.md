# WordPress Multi-site API — v1.14

Create and delete temporary WordPress Multisite sites with REST API requests. This version supports **subdomain** and **subfolder** WordPress networks without changing established request and response shapes.

## Installation and upgrade

1. Back up the database and existing plugin files. Prefer testing on staging.
2. Install/replace the `multi-plugin-sandbox` directory and network-activate the plugin as applicable. Do not install a second copy alongside the original.
3. Open **Network Admin → Sandbox Settings**. Check your existing secret, Base Domain, password email checkbox, and **Default Site URL Mode**.
4. Create and delete a disposable site through the API. Verify existing sites before deploying broadly.

The plugin **does not migrate, rename or rewrite existing sites**. Moving from a subdomain Multisite installation to a subfolder installation is a separate WordPress migration/reinstallation, not something this plugin does.

## URL mode selection and defaults

The **Default Site URL Mode** setting offers:

| Setting | Behaviour |
| --- | --- |
| **Automatic** (`auto`) | Detect using WordPress `is_subdomain_install()`. Use `subdomain` for subdomain networks, `subfolder` for subfolder networks. **Default if no choice has been saved** (including a fresh installation). |
| **Subdomain** (`subdomain`) | Force subdomain site creation; rejected if the network is configured for subfolders. |
| **Subfolder** (`subfolder`) | Force subfolder site creation; rejected if the network is configured for subdomains. |

**Upgrade compatibility:** Any already-saved `sandbox_site_mode` selection from v1.13 remains unchanged. For installations that have never saved this option, Automatic detects the current network and therefore retains subdomain creation on subdomain networks. The setting affects **new creations only**.

**API override:** A create request may include optional `site_mode` or `url_mode` (alias). If both are supplied, `site_mode` takes precedence. `auto` uses WordPress detection, while a specified subdomain/subfolder mode must match WordPress configuration. No parameter uses the backend default. Invalid/mismatched modes return HTTP 400 before attempting user or site creation.

### Base Domain

This existing setting continues to define the hostname suffix for subdomain creation, e.g. `postaccessmanager.wpdemo.uk` creates `demo.postaccessmanager.wpdemo.uk`. Do **not** include `https://` or a trailing `/`. The plugin trims accidental surrounding `/` when it constructs a hostname. The setting is **not used for subfolder creation**; that mode uses the current network's domain and path. The existing fallback value `wpdemo.uk` is retained for compatibility, so set the correct Base Domain explicitly.

### Other existing settings

- **Secret Key:** Shared secret required by both API endpoints. The existing stored value is preserved. Replace any default secret with a unique, strong secret before exposing the API.
- **Disable Admin Password Change Emails:** Existing checkbox and stored setting are preserved.
- **Network mode:** Configure WordPress Multisite itself for either subdomain or subfolder mode. Switching the plugin setting **does not** reconfigure WordPress, DNS, SSL or web-server routing.

## API reference

WordPress REST base URL: `https://YOUR-NETWORK-DOMAIN/wp-json/`

### Create a site

`POST /wp-json/sandbox/v1/create/`

Request (JSON or equivalent WP REST request parameters):

```json
{
  "secret": "YOUR-SECRET",
  "slug": "customer-demo",
  "title": "Customer Demo",
  "email": "customer@example.com"
}
```

The **four existing fields remain unchanged**. `site_mode` / `url_mode` are optional:

```json
{
  "secret": "YOUR-SECRET",
  "slug": "customer-demo",
  "title": "Customer Demo",
  "email": "customer@example.com",
  "site_mode": "auto"
}
```

Examples of optional overrides: `"site_mode": "subdomain"`, `"site_mode": "subfolder"`, `"site_mode": "auto"`, or `"url_mode": "auto"`. A mode that doesn't match the network is rejected; it cannot convert the network.

Successful response (same keys as before):

```json
{
  "success": true,
  "site_id": 42,
  "url": "https://customer-demo.example.com"
}
```

On a subfolder network, the same request can return:

```json
{
  "success": true,
  "site_id": 42,
  "url": "https://example.com/customer-demo/"
}
```

A subfolder network installed at `/network/` would create `/network/customer-demo/`. URL format is determined by the network; the response keys remain the same.

### Delete a site (unchanged)

`POST /wp-json/sandbox/v1/delete/`

```json
{
  "secret": "YOUR-SECRET",
  "site_id": 42
}
```

Existing response shape:

```json
{
  "success": true,
  "deleted": 42,
  "deleted_user": null
}
```

**Caution:** Delete permanently removes the selected site, and the original plugin also deletes the site's admin user when no longer assigned to other sites. Use a disposable site in testing. The deletion endpoint is unchanged in v1.14.

### Example cURL calls

Create with backend default (no new fields required):

```bash
curl -X POST 'https://example.com/wp-json/sandbox/v1/create/' \
  -H 'Content-Type: application/json' \
  -d '{"secret":"YOUR-SECRET","slug":"customer-demo","title":"Customer Demo","email":"customer@example.com"}'
```

Create with explicit automatic detection:

```bash
curl -X POST 'https://example.com/wp-json/sandbox/v1/create/' \
  -H 'Content-Type: application/json' \
  -d '{"secret":"YOUR-SECRET","slug":"customer-demo","title":"Customer Demo","email":"customer@example.com","site_mode":"auto"}'
```

Delete a disposable site:

```bash
curl -X POST 'https://example.com/wp-json/sandbox/v1/delete/' \
  -H 'Content-Type: application/json' \
  -d '{"secret":"YOUR-SECRET","site_id":42}'
```

## Compatibility checklist

- Existing REST routes (`sandbox/v1/create/` and `sandbox/v1/delete/`) are unchanged.
- Existing required request fields, security secret check and response keys are unchanged.
- Existing site records and database tables are not migrated.
- Explicit v1.13 backend mode is retained after upgrading.
- Legacy requests without a mode work on the matching WordPress network type.
- Wrong mode is rejected before writing new users or sites.
- Existing optional backend settings are retained.



