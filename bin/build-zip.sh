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

rsync -a \
	--exclude '.git' \
	--exclude '.github' \
	--exclude 'bin' \
	--exclude 'docs' \
	--exclude '*.zip' \
	--exclude '.DS_Store' \
	--exclude 'CHANGELOG.md' \
	--exclude 'version.txt' \
	--exclude 'release-please-config.json' \
	--exclude '.release-please-manifest.json' \
	"${ROOT}/" "${BUILD}/${SLUG}/"

( cd "$BUILD" && zip -rq "${SLUG}.zip" "$SLUG" )

rm -f "$OUT"
mv "${BUILD}/${SLUG}.zip" "$OUT"

echo "built $OUT"
