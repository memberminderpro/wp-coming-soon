# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A single-file-bootstrapped WordPress plugin (`mmp-coming-soon.php` + `includes/`)
that replaces the front end with a self-contained holding page for anonymous
visitors. No build step, no package manager, no test suite — plain PHP, CSS, JS,
and bundled woff2 fonts.

`README.md` documents behaviour and the release/update system in depth;
`CONTRIBUTING.md` documents the branch and release process. Read those before
changing anything in the update or release path.

## Commands

There is no build, no dependency install, and no test runner. The two checks CI
runs, and the two to run before opening a PR:

```sh
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l
bin/build-zip.sh /tmp/mmp-coming-soon.zip
```

CI (`.github/workflows/pr-quality.yml`) additionally asserts the zip has exactly
one top-level folder named `mmp-coming-soon`, that no build tooling (`bin/`,
`.github/`, changelog, release-please files) ships inside it, and that the plugin
header `Version:` and the `MMPCS_VERSION` constant agree. PHP lint runs on 7.4
and 8.4 — the header claims `Requires PHP: 7.4`, so no 8.x-only syntax.

Verifying behaviour means installing the plugin on a WordPress site; the admin
screen has a **Preview page** action that renders the holding page while the gate
is off.

## Architecture

`mmp-coming-soon.php` defines the constants (`MMPCS_VERSION`, `MMPCS_OPTION`,
`MMPCS_UPDATE_MANIFEST`, …), requires each class, and boots them on
`plugins_loaded`. Admin-only classes are required and initialised only when
`is_admin()`.

All classes are static (`Class::init()` registering hooks); there are no
instances and no autoloader.

| Class | Role |
| --- | --- |
| `MMPCS_Settings` | Schema, defaults, merged read with a runtime cache, and every sanitiser. Also owns presets, the one-slot undo, per-section reset, and the legacy-shape migrations. |
| `MMPCS_Frontend` | The gate. Hooks `template_redirect` at priority 0, decides whether to serve the page. |
| `MMPCS_Renderer` | Emits the complete HTML document and `exit`s. |
| `MMPCS_Aurora` | Builds the animated blob markup and its per-blob custom properties. |
| `MMPCS_Updater` | Self-hosted update checks against a static GitHub manifest. |
| `MMPCS_Admin` | The one options form (six client-side tabs, one save, one sanitise callback). |
| `MMPCS_Tools` | Reset/undo/preset/export/import, each its own `admin_post_mmpcs_*` endpoint with its own nonce. |
| `MMPCS_Admin_Bar` | Always-visible on/off indicator; loads on front end too, which is why `MMPCS_MENU_SLUG` is a global constant rather than a class const. |
| `MMPCS_Preview` | `admin-post` endpoint that renders unsaved posted settings through the ordinary sanitiser and renderer, without saving. Feeds the live preview pane. |

### Invariants that must not be broken

These are load-bearing; each has a failure mode that is not obvious from the code
alone.

* **Logos and buttons are repeaters with migrations.** `logos` replaced a fixed
  primary/secondary pair, and buttons gained a `name` distinct from their
  optional visible `label`. `migrate_legacy_logos()` and
  `migrate_legacy_buttons()` run on read and on import, are idempotent, and
  write nothing; old keys simply stop being persisted at the next save.
* **A button's accessible name must contain its visible text.** WCAG 2.5.3: a
  name that replaces the visible label makes the control unreachable by voice.
  The renderer drops a clashing name rather than emitting it.
* **One option row.** Everything — settings, presets, undo snapshot — lives in
  `mmpcs_settings`. No pages, posts, tables, uploads, user meta, or cron events.
  That is what lets `uninstall.php` remove every trace. Anything a future version
  persists must also be added to the uninstall sweep.
* **The renderer never calls `wp_head()` or `wp_footer()`.** That is the whole
  mechanism preventing the host theme or another plugin from enqueueing onto the
  page.
* **No AutomaticCSS, Etch, or theme dependency.** Every length in the stylesheet
  is an absolute `px` or viewport unit, so a host site's `62.5%` root font size
  cannot break the layout. Do not introduce `rem`/`em` in `assets/css/`.
* **`MMPCS_Frontend::ALWAYS_OPEN` is hard-coded** so an administrator cannot lock
  themselves out of `/wp-admin`, login, REST, cron, feeds, or sitemaps.
* **Manifests live on the `manifests` branch only.** Publishing them from `main`
  or `develop` would mean a develop→main merge briefly advertised a beta build to
  stable sites. Each manifest also names its own channel and the plugin ignores a
  mismatched one.

### Traps that have already cost a day

Each of these was a real bug, not a hypothetical.

* **`sanitize()` runs on every write, not just form submissions.**
  `register_setting()` hooks it to `sanitize_option_mmpcs_settings`, so
  `update_option()` calls it again on whatever `write()` just built. Anything
  `sanitize()` reconstructs from `get_option()` is therefore reading the
  pre-write value. That silently destroyed every preset and undo snapshot at the
  moment it was saved. Values present in the input must win; the stored copy is
  only a fallback.
* **A class that sets `display` beats `[hidden]`.** `.mmpcs-field { display: flex }`
  outranks the browser's `[hidden] { display: none }`, so hiding a field with the
  attribute did nothing while the toggles reported themselves as off. There is
  now one `[hidden] { display: none !important }` rule in `admin.css`; keep it.
* **`display: flex` on a `<td>` or `<th>` stops it being a table cell.** The
  column drops out of the table's sizing, which shortens header rules and makes
  buttons wrap. Put the flex box *inside* the cell.
* **Settings rows size against the settings column, not the viewport.** The
  preview pane takes half a wide screen, so a media query sees "wide" while the
  fields are cramped. `.mmpcs-form` is a CSS container and the rows use
  `@container mmpcs-settings`.
* **Row order is display order.** Repeaters store no weight; moving a row moves
  it on the page. Do not add a sort field.

### Verifying admin UI

Reasoning about CSS has repeatedly shipped bugs here. Render the real markup and
look at it: stub the WordPress functions, `eval()` the private method out of
`class-mmpcs-admin.php`, write the output into a page that loads the actual
`admin.css` and `admin.js`, serve it over `http://127.0.0.1`, and drive it in a
browser. Measure geometry (`getBoundingClientRect()`), do not eyeball it. This
catches clipped controls, source-order specificity losses, and layout that only
misbehaves at a particular column width.

## Versioning and releases

Versions are written by release-please, never by hand. Do not edit:
the version in `mmp-coming-soon.php` (two `x-release-please-*` annotated blocks),
`CHANGELOG.md`, `version.txt`, either `.release-please-manifest*.json`, or
anything on the `manifests` branch.

Branches: work off `develop`; `develop` → `main` is the only path to production.
`develop` releases are `-beta` pre-releases (`release-please-config.prerelease.json`),
`main` releases are stable. Merging a stable release PR ships *nothing* — the
manual **Deploy Release to Customer Sites** workflow publishes
`manifests/stable.json`, and that is what reaches customer sites.

PR titles must be Conventional Commits: merges are squashed, so the title becomes
the commit message release-please reads. A wrong title fails CI (and would
otherwise silently skip the release).

After every stable release, one PR off `develop` must (1) merge `main` back into
`develop` and (2) baseline `.release-please-manifest.prerelease.json` on the
released version — otherwise the beta line starts trailing stable. See
CONTRIBUTING.md → "After every stable release".

**Do that sync as a real merge, never a squash.** PR #21 was squashed, so `main`
is not an ancestor of `develop`: release-please could not find the `v1.3.0`
boundary, walked back through the whole history, and cut a `1.4.0-beta` that
contained no new feature. Until a genuine merge repairs the ancestry, expect the
same phantom bump after the next stable release.

Beta manifests are served from `raw.githubusercontent.com`, which is Fastly-
fronted with a five-minute TTL and normalises away the cache-busting query
string. A freshly published beta takes a few minutes to become visible however
hard anyone presses the update button.
