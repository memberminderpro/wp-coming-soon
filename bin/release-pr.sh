#!/usr/bin/env bash
#
# Open the release pull request for a branch.
#
# This is a developer step on purpose. GitHub Actions is not given permission
# to open pull requests; it only tags and publishes a release that a human has
# already reviewed and merged.
#
# Usage:
#   bin/release-pr.sh            # release develop  -> a -beta.N pre-release
#   bin/release-pr.sh main       # release main     -> a production release
#
# Requires the GitHub CLI, authenticated with repo access.

set -euo pipefail

BRANCH="${1:-develop}"

if [[ "$BRANCH" != "main" && "$BRANCH" != "develop" ]]; then
	echo "error: branch must be main or develop (got '${BRANCH}')" >&2
	exit 1
fi

cd "$(dirname "$0")/.."

REPO="$(gh repo view --json nameWithOwner -q .nameWithOwner)"

if [[ "$BRANCH" == "develop" ]]; then
	CONFIG=".github/release-please-config.develop.json"
	MANIFEST=".github/.release-please-manifest.develop.json"
else
	CONFIG="release-please-config.json"
	MANIFEST=".release-please-manifest.json"
fi

echo "==> Opening the release pull request for ${BRANCH} on ${REPO}"

GITHUB_TOKEN="$(gh auth token)" npx --yes release-please release-pr \
	--token="$(gh auth token)" \
	--repo-url="https://github.com/${REPO}" \
	--target-branch="$BRANCH" \
	--config-file="$CONFIG" \
	--manifest-file="$MANIFEST"

echo
echo "==> Review and merge it. Merging tags the release, attaches the zip,"
echo "    and publishes the ${BRANCH/develop/beta}" | sed 's/main/stable/' 
echo "    manifest that installed sites poll."
