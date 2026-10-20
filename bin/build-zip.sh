#!/usr/bin/env bash
#
# Build the distributable plugin zip.
#
# The archive's single top-level folder must be the plugin slug, so that
# WordPress upgrades in place instead of installing a second copy alongside.
#
# Usage: bin/build-zip.sh [output-path]

set -euo pipefail

cd "$(dirname "$0")/.."
ROOT="$(pwd)"
SLUG="mmp-coming-soon"
OUT="${1:-${ROOT}/${SLUG}.zip}"

BUILD="$(mktemp -d)"
trap 'rm -rf "$BUILD"' EXIT

mkdir -p "${BUILD}/${SLUG}"

# The plugin ships no dotfiles at all, so excluding every one of them is both
# shorter and safer than naming them: .git, .github and .editorconfig were the
# known ones, but a working tree also collects .claude and .remember, and a
# denylist only ever excludes the tools somebody has already thought of. Those
# two were, in fact, being copied into locally built archives.
#
# Everything else is anchored to the repo root with a leading slash. Unanchored,
# rsync matches a name at any depth, so 'bin' would also drop a bin/ folder
# inside the plugin's own code.
rsync -a \
	--exclude '.*' \
	--exclude '*.zip' \
	--exclude '/bin' \
	--exclude '/docs' \
	--exclude '/CLAUDE.md' \
	--exclude '/CHANGELOG.md' \
	--exclude '/CONTRIBUTING.md' \
	--exclude '/version.txt' \
	--exclude '/release-please-config*.json' \
	"${ROOT}/" "${BUILD}/${SLUG}/"

( cd "$BUILD" && zip -rq "${SLUG}.zip" "$SLUG" )

rm -f "$OUT"
mv "${BUILD}/${SLUG}.zip" "$OUT"

echo "built $OUT"
