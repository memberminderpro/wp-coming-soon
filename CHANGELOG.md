# Changelog

## 1.1.1

### Bug Fixes

* never refresh the update manifest during a front-end request. `site_transient_update_plugins` can be read on the front end, and a cold cache put a blocking ten-second call to GitHub in the middle of a visitor's page load.

## 1.1.0

### Features

* Automatic updates from GitHub releases, with stable and beta channels and a per-site opt-out.

## 1.0.0

### Features

* First release. A self-contained coming soon page with an animated aurora background, converted from the Etch component.
