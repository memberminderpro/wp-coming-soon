#!/usr/bin/env python3
"""Generate the update manifest that installed sites poll.

Written to the "manifests" branch, which no release branch ever merges into.
Serving manifests from main and develop instead would mean a develop-to-main
merge briefly advertised a beta build to every stable site.

Usage: bin/manifest.py <channel> <version> <repo> <output-path>
"""

import json
import sys
import datetime

CHANNEL_BRANCH = {"stable": "main", "beta": "develop"}


def main() -> int:
    if len(sys.argv) != 5:
        print(__doc__, file=sys.stderr)
        return 1

    channel, version, repo, out = sys.argv[1:5]

    if channel not in CHANNEL_BRANCH:
        print(f"unknown channel: {channel}", file=sys.stderr)
        return 1

    manifest = {
        "name": "MMP Coming Soon",
        "slug": "mmp-coming-soon",
        "channel": channel,
        "version": version,
        "requires": "6.0",
        "tested": "7.0",
        "requires_php": "7.4",
        "author": '<a href="https://memberminderpro.com/">Member Minder Pro, LLC</a>',
        "homepage": f"https://github.com/{repo}",
        # {version} is expanded by the plugin, so a release only has to change
        # the version field.
        "download_url": (
            f"https://github.com/{repo}/releases/download/v{{version}}/mmp-coming-soon.zip"
        ),
        "changelog_url": (
            f"https://raw.githubusercontent.com/{repo}/"
            f"{CHANNEL_BRANCH[channel]}/CHANGELOG.md"
        ),
        "last_updated": datetime.datetime.now(datetime.timezone.utc).strftime(
            "%Y-%m-%d %H:%M:%S"
        ),
        "sections": {
            "description": (
                "A self-contained coming soon page with an animated aurora "
                "background. Replaces the front end for anonymous visitors "
                "while the site is being built. No theme, plugin, or framework "
                "dependencies."
            )
        },
    }

    with open(out, "w", encoding="utf-8") as handle:
        json.dump(manifest, handle, indent=2, ensure_ascii=False)
        handle.write("\n")

    print(f"wrote {out} ({channel} {version})")
    return 0


if __name__ == "__main__":
    sys.exit(main())
