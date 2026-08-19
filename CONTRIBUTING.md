# Contributing

## Branches

| Branch | Meaning |
| --- | --- |
| `main` | Production. Every customer site on the stable channel tracks it. |
| `develop` | Integration. Cutting a release here produces a `-beta.N` pre-release. |
| `manifests` | Machine-written update manifests. Never merge anything into it. |

Work happens on a branch off `develop` and reaches `main` only through
`develop`.

## Commit and pull request titles

Releases are generated from [Conventional Commits](https://www.conventionalcommits.org/).
Because merges are squashed, **the pull request title becomes the commit
message** — that title is what release-please reads.

| Prefix | Effect on the version |
| --- | --- |
| `fix:` | patch — 1.1.0 → 1.1.1 |
| `feat:` | minor — 1.1.0 → 1.2.0 |
| `feat!:` or a `BREAKING CHANGE:` footer | major — 1.1.0 → 2.0.0 |
| `docs:` `chore:` `ci:` `refactor:` `style:` `test:` | no release |

A CI check rejects a pull request whose title is not a conventional commit. It
exists because the failure mode is silent otherwise: release-please simply does
not cut a release, and the reason is not obvious.

## Releasing

You do not edit version numbers, but you do decide when a release happens.

```sh
bin/release-pr.sh            # release develop -> a -beta.N pre-release
bin/release-pr.sh main       # release main    -> a production release
```

That opens a release pull request containing the version bump and the changelog
entry, authored by you. Review it like any other pull request. Merging it is
what triggers the release: GitHub Actions tags it, builds the zip, attaches it
to the release, and publishes the manifest that installed sites poll.

GitHub Actions is deliberately **not** allowed to open pull requests. It runs
with `skip-github-pull-request: true` and `contents: write` only, so nothing
reaches a customer site without a human merging it first.

Never hand-edit:

* the version in `mmp-coming-soon.php` (two annotated blocks)
* `CHANGELOG.md`
* `version.txt`
* `.release-please-manifest.json` or its develop counterpart
* anything on the `manifests` branch

## Before you open a pull request

```sh
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l
bin/build-zip.sh /tmp/mmp-coming-soon.zip
```

CI runs both, plus checks that the archive has exactly one top-level folder
named for the plugin slug, that no build tooling ships inside it, and that the
plugin header and the `MMPCS_VERSION` constant agree.

## Design constraints worth preserving

* **One option row.** The plugin stores everything in `mmpcs_settings` and
  creates no pages, posts, tables, uploads, user meta, or cron events. That is
  what lets `uninstall.php` remove every trace. Anything a future version
  persists must be added to the uninstall sweep.
* **The renderer never calls `wp_head()` or `wp_footer()`.** That is the
  mechanism that makes the page immune to the host site's theme and plugins.
* **No dependency on AutomaticCSS or Etch.** Every length is an absolute `px`
  or viewport unit, so the page cannot be broken by a host site's root font
  size.
