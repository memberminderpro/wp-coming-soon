# Changelog

## [1.2.0](https://github.com/memberminderpro/wp-coming-soon/compare/v1.1.1...v1.2.0) (2026-08-19)


### Features

* promote the admin bar status button and self-hosted update pipeline to production ([055c8e5](https://github.com/memberminderpro/wp-coming-soon/commit/055c8e554e86afe00a65826c2157697255477d5c))
* replace the dashboard notice with an admin bar status button ([#5](https://github.com/memberminderpro/wp-coming-soon/issues/5)) ([aa5d7b8](https://github.com/memberminderpro/wp-coming-soon/commit/aa5d7b8859e7fb229ad74e0b0e9503a7b97e9064))


### Bug Fixes

* admin bar button opens the settings page and stays readable on hover ([#9](https://github.com/memberminderpro/wp-coming-soon/issues/9)) ([eb577ad](https://github.com/memberminderpro/wp-coming-soon/commit/eb577ad0da1f032bd0ecb6a8d32024357d6026ee))

## [1.2.0-beta.1](https://github.com/memberminderpro/wp-coming-soon/compare/v1.2.0-beta...v1.2.0-beta.1) (2026-08-19)


### Bug Fixes

* admin bar button opens the settings page and stays readable on hover ([#9](https://github.com/memberminderpro/wp-coming-soon/issues/9)) ([eb577ad](https://github.com/memberminderpro/wp-coming-soon/commit/eb577ad0da1f032bd0ecb6a8d32024357d6026ee))

## [1.2.0-beta](https://github.com/memberminderpro/wp-coming-soon/compare/v1.1.1...v1.2.0-beta) (2026-08-19)


### Features

* replace the dashboard notice with an admin bar status button ([#5](https://github.com/memberminderpro/wp-coming-soon/issues/5)) ([aa5d7b8](https://github.com/memberminderpro/wp-coming-soon/commit/aa5d7b8859e7fb229ad74e0b0e9503a7b97e9064))
* self-contained coming soon plugin with GitHub release updates ([db3056d](https://github.com/memberminderpro/wp-coming-soon/commit/db3056d3b50c923db4157050ec607822bf3f56dd))


### Bug Fixes

* never refresh the update manifest during a front-end request ([#1](https://github.com/memberminderpro/wp-coming-soon/issues/1)) ([e5d7cd5](https://github.com/memberminderpro/wp-coming-soon/commit/e5d7cd5570125b1ccbbdcae585eb07fe7af47bdc))

## 1.1.1

### Bug Fixes

* never refresh the update manifest during a front-end request. `site_transient_update_plugins` can be read on the front end, and a cold cache put a blocking ten-second call to GitHub in the middle of a visitor's page load.

## 1.1.0

### Features

* Automatic updates from GitHub releases, with stable and beta channels and a per-site opt-out.

## 1.0.0

### Features

* First release. A self-contained coming soon page with an animated aurora background, converted from the Etch component.
