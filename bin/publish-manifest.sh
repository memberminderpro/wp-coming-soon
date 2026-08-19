#!/usr/bin/env bash
#
# Publish a channel's update manifest to the "manifests" branch.
#
# That branch is orphaned on purpose and nothing merges into it. Publishing the
# manifests from main and develop instead would mean a develop-to-main merge
# briefly advertised a beta build to every site on the stable channel.
#
# Usage: bin/publish-manifest.sh <stable|beta> <version> <owner/repo>
#
# Requires GH_TOKEN in the environment.

set -euo pipefail

CHANNEL="${1:?channel required}"
VERSION="${2:?version required}"
REPO="${3:?owner/repo required}"

case "$CHANNEL" in
	stable|beta) ;;
	*) echo "error: channel must be stable or beta (got '${CHANNEL}')" >&2; exit 1 ;;
esac

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

git config --global user.name  "github-actions[bot]"
git config --global user.email "41898282+github-actions[bot]@users.noreply.github.com"

REMOTE="https://x-access-token:${GH_TOKEN}@github.com/${REPO}.git"

if ! git clone --depth 1 --branch manifests "$REMOTE" "${WORK}/manifests" 2>/dev/null; then
	git clone --depth 1 "$REMOTE" "${WORK}/manifests"
	git -C "${WORK}/manifests" checkout --orphan manifests
	git -C "${WORK}/manifests" rm -rf . >/dev/null 2>&1 || true
fi

python3 "${ROOT}/bin/manifest.py" "$CHANNEL" "$VERSION" "$REPO" "${WORK}/manifests/${CHANNEL}.json"

cd "${WORK}/manifests"
git add "${CHANNEL}.json"

if git diff --cached --quiet; then
	echo "manifest unchanged; nothing to publish"
	exit 0
fi

git commit -m "chore: publish ${CHANNEL} manifest ${VERSION}"
git push origin manifests

echo "published ${CHANNEL} manifest at ${VERSION}"
