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
| Content | The logo repeater and its placements; badge, heading, description |
| Buttons | Main and support button repeaters — name, link, style, optional button text and image per row |
| Footer | Company name and link, legal text, legal-link repeater |
| Background | Aurora on/off, motion, base colour, blob colours, size, blur, speed, intensity |
| Colors | Accent, accent hover, button text, navy, crimson, off-white |
| Updates | Version status, update channel, a manual check, and the automatic-update switch |

### Buttons

Every button has a **name**, and that is all most buttons need: it is the text on
the button, and it is what the link is called to a screen reader.

Two optional extras sit behind icons beside the name, so a plain button is three
fields rather than six:

* **Button text** — show something shorter or different from the name.
* **Image** — use an image instead of text. The name becomes its alt text, and
  the style control disappears, because a fill and a border around a badge that
  carries its own shape is chrome on top of chrome.

Where the name and the visible text differ, the name is used as the link's
accessible name **only if it contains the visible text** — "Learn more about
hosting" over a button reading "Learn more" is fine; "Hosting CTA" is not, and is
ignored. That is WCAG 2.5.3, and the reason is practical: someone using voice
control says what they can see, so an accessible name that replaces the visible
text makes the button unreachable. The settings screen shows what will be
announced as you type, and warns when a name is being ignored.

### Recent history

Applying a preset, resetting a section, importing a file or stepping back all
record what the design looked like immediately beforehand, under **Presets →
Recent history**. Twenty-five entries are kept, newest first, each labelled with
what happened next — so getting lost is a matter of stepping backwards until it
looks right, rather than having remembered to save something first.

Stepping back records the current design too, so it is reversible in turn, and
the screen then offers to keep what you landed on as a named preset.

History lives in its own option and is never autoloaded: the coming soon gate
reads the settings row on every request it evaluates, and history has no business
being in it.

### Logos

There is no primary logo. Every logo is a row in one repeater, and each row
chooses its own slot: the top of the page, below the badge, the heading, the
description or the buttons, or above the footer text. Row order is display
order, so several logos assigned to the same slot render together in the order
you arrange them — which is what turns the feature into a sponsor or partner
block, anywhere on the page.

Where a slot holds more than one logo, it can arrange them side by side
(wrapping and centred, the default) or stacked. Width is per logo, so a wide
wordmark and a square badge can sit in the same row; picking an image from the
media library shows its natural width as a starting point.

Alt text and the link's ARIA label are separate fields, because they do
different jobs: alt describes the image, and the ARIA label names where the
link goes.

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
from Conventional Commit messages. Nobody edits a version number by hand, and
every step runs in GitHub Actions.

```
feature branch ──PR──▶ develop ──▶ release PR ──merge──▶ v1.2.0-beta  (pre-release)
                                                          beta manifest published
                                                          canary site updates

develop ───────PR──▶ main ─────▶ release PR ──merge──▶ v1.2.0        (release)
                                                          nothing ships yet
                                                          ▼
                                              Deploy workflow (manual)
                                                          ▼
                                                stable manifest published
                                                all customer sites update
```

1. Open a PR into `develop`. The PR **title** must be a Conventional Commit
   (`feat: …`, `fix: …`), because a squash merge uses it as the commit message.
   A CI check enforces this: a bad title means release-please silently skips
   the release.
2. Merging it opens or updates a release PR against `develop`. Merging *that*
   tags a pre-release, builds the zip, and publishes the beta manifest, so the
   canary picks it up.
3. When the beta looks good, open a PR from `develop` into `main`. Merging its
   release PR tags and builds the stable release — but publishes nothing.
4. **Run the Deploy workflow** from the Actions tab against the stable tag.
   That publishes `manifests/stable.json`, which is what makes every customer
   site see the update. It refuses anything that is not a plain `vX.Y.Z` tag,
   refuses a release marked as a prerelease, and refuses a release whose
   package is missing or not downloadable.

Releasing and shipping are deliberately separate. A stable release can sit on
GitHub indefinitely; no customer site knows about it until someone dispatches
the deploy.

### Identity

release-please runs as the **`mmpro-release-automation`** GitHub App, not as
GitHub Actions, using the org-level `RELEASE_APP_ID` and
`RELEASE_APP_PRIVATE_KEY` secrets. Two reasons, both load-bearing:

* The organization forbids GitHub Actions from creating pull requests. A
  GitHub App is a separate identity, so the policy does not apply to it.
* Events created with the default `GITHUB_TOKEN` do not trigger other
  workflows. A tag pushed by `GITHUB_TOKEN` would never fire the build.

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

Deleting the plugin removes everything. It stores two option rows
(`mmpcs_settings` and `mmpcs_history`) and creates no pages, posts, custom tables, uploads, user
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
