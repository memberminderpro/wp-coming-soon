# MMP Coming Soon

A self-contained WordPress coming soon page. Drop it on any site, switch it on,
and visitors who are not signed in get a branded holding page instead of a
half-built site. Signed-in users always see the real site.

It is a standalone conversion of an Etch component and its animated aurora
background — no Etch, no AutomaticCSS, no theme, no build step.

## Why it works on any site

When the gate fires, the plugin renders its own complete HTML document and
exits. It never calls `wp_head()` or `wp_footer()`, so no theme and no other
plugin can enqueue anything onto the page. The only assets loaded are the
plugin's own CSS, JS, and self-hosted fonts.

Every length in the stylesheet is an absolute `px` or viewport unit, so the page
is immune to a host site's root font size — the failure mode that broke the
original component when it moved between sites configured at `62.5%` and `100%`.

## Settings

**Coming Soon** in the admin menu. Six tabs:

| Tab | What it controls |
| --- | --- |
| Visibility | The on/off switch and the always-public path allowlist |
| Content | Logo image, alt text, link, ARIA label, width; badge, heading, description |
| Buttons | Main and support button repeaters — label, link, style per row |
| Footer | Company name and link, legal text, legal-link repeater |
| Background | Aurora on/off, motion, base colour, blob colours, size, blur, speed, intensity |
| Colors | Accent, accent hover, button text, navy, crimson, off-white |
| Updates | Version status, update channel, a manual check, and the automatic-update switch |

**Preview page** opens the holding page in a new tab even while the gate is off.

## Gate behaviour

Anonymous visitors are shown the coming soon page for every front-end URL,
except:

* anything in the always-public allowlist you configure,
* `/wp-admin`, `/wp-login.php`, `/wp-json`, `/xmlrpc.php`, `/wp-cron.php`,
  `/wp-content`, `/wp-includes`, `/robots.txt`, `/favicon.ico`, `/wp-sitemap*`,
  `/.well-known` — hard-coded so you cannot lock yourself out,
* feeds, trackbacks, REST, AJAX, cron, and WP-CLI.

Responses are `200` with `noindex, nofollow` (both a meta tag and an
`X-Robots-Tag` header) and a revalidating `Cache-Control`, so a page cache does
not keep serving the holding page after you switch it off.

Developers can override the decision:

```php
add_filter( 'mmpcs_should_gate', function ( $gate ) {
	return $gate;
} );
```

## Updates

Installed copies check a manifest on GitHub and update themselves through
WordPress's own update pipeline — the same badge, the same button, and, when
automatic updates are on, the same unattended cron install. There is no
dependency on any update service or companion plugin.

### Channels

| Channel | Source branch | Who should be on it |
| --- | --- | --- |
| `stable` | `main` | Every customer site. The default. |
| `beta` | `develop` | One canary site you control. |

Set the channel per site under **Coming Soon → Updates**, or pin it from
`wp-config.php`, which overrides the setting:

```php
define( 'MMPCS_UPDATE_CHANNEL', 'beta' );
```

Manifests are published to a dedicated `manifests` branch that no release
branch ever merges into. Publishing them from `main` and `develop` instead
would mean that merging `develop` into `main` briefly advertised a beta build
to every stable site. As a second guard, each manifest names its own channel,
and the plugin ignores a manifest whose channel does not match the site's.

### Release flow

Releases are driven by [release-please](https://github.com/googleapis/release-please)
from Conventional Commit messages. Nobody edits a version number by hand.

```
feature branch ──PR──▶ develop ──▶ release PR ──merge──▶ v1.2.0-beta.1  (pre-release)
                                                          beta sites update

develop ───────PR──▶ main ─────▶ release PR ──merge──▶ v1.2.0           (release)
                                                          all sites update
```

1. Open a PR into `develop`. The PR **title** must be a Conventional Commit
   (`feat: …`, `fix: …`), because a squash merge uses it as the commit message.
   A CI check enforces this: a bad title means release-please silently skips
   the release.
2. Merging opens (or updates) a release PR against `develop`. Merging *that*
   tags a `-beta.N` pre-release, attaches the zip, and publishes the beta
   manifest.
3. When the beta looks good, open a PR from `develop` into `main`. Merging its
   release PR cuts the stable release and publishes the stable manifest.

Sites pick up a release within six hours, or immediately from
**Coming Soon → Updates → Check for updates now**.

### Version numbers

release-please rewrites the version in `mmp-coming-soon.php` in two places,
both marked with annotations:

```php
 * x-release-please-start-version
 * Version:           1.1.0
 * x-release-please-end
```

The markers sit inside the plugin header docblock, where WordPress's parser
ignores them — it only reads `Key: value` lines. CI asserts that the header and
the `MMPCS_VERSION` constant agree, and that both match the tag.

### Hardening

* The manifest is a static file, not the GitHub releases API. The API allows 60
  anonymous requests per hour per IP; customer sites sharing a host IP would
  exhaust that and silently stop seeing updates.
* A package is only installed when it comes from `github.com` over HTTPS. A
  manifest pointing anywhere else is ignored.
* `Update URI:` in the plugin header pins updates to this repository, so
  WordPress.org can never serve an unrelated plugin that happens to share the
  `mmp-coming-soon` slug.

To point a fork elsewhere, change `MMPCS_UPDATE_MANIFEST` in the main plugin
file (and the matching `Update URI:` header), or filter it at runtime:

```php
add_filter( 'mmpcs_update_manifest_url', function ( $url, $channel ) {
	return "https://example.com/mmp-coming-soon/{$channel}.json";
}, 10, 2 );
```

## Uninstall

Deleting the plugin removes everything. It stores exactly one option row
(`mmpcs_settings`) and creates no pages, posts, custom tables, uploads, user
meta, or cron events, so nothing survives removal. `uninstall.php` also sweeps
for any `mmpcs_*` option, transient, user meta, or scheduled hook, and repeats
the sweep on every site of a multisite network.

Deactivating, as opposed to deleting, keeps your settings so that switching the
plugin back on restores them.

## Fonts

Syne and Plus Jakarta Sans are bundled as `woff2` (latin and latin-ext subsets,
about 69 KB total) and served from the plugin. Both are licensed under the SIL
Open Font License 1.1, which permits redistribution; see `assets/fonts/LICENSE`.

## Requirements

WordPress 6.0+, PHP 7.4+. No other dependencies.
