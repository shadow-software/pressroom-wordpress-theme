#!/usr/bin/env bash
#
# Screenshot every page of the theme, at desktop and mobile, in one command.
#
#   ./scripts/shoot.sh                    the local sandbox (localhost:8080)
#   ./scripts/shoot.sh --live             both live sites
#   ./scripts/shoot.sh --site https://…   any one origin
#   ./scripts/shoot.sh --only cart        just routes matching "cart"
#   ./scripts/shoot.sh --full             full-page, not just the fold
#
# Then open .shots/index.html — desktop and mobile side by side, every route.
#
# WHY A WRAPPER: this exists so that "look at the page" is one word. If it were
# three commands and a node_modules dance, it would not get run, and the whole
# point of it is that it gets run every single time.
#
# Playwright is resolved from wherever it already is (a repo-local install, a
# global one, or the npm cache via npx) — it is NOT vendored into the repo, and
# no install happens without you asking for it.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# Playwright's browsers live in ~/.cache/ms-playwright and are shared across every
# project on the machine. The node package is the only thing we need to locate.
if [ -d "$ROOT/node_modules/playwright" ]; then
  RUN=( node )
elif npm ls -g playwright --depth=0 >/dev/null 2>&1; then
  RUN=( node )
  export NODE_PATH="$(npm root -g)"
else
  cat <<'EOF'
Playwright is not installed, and this script will not install it for you.

  npm install --no-save playwright

(The browsers themselves are already in ~/.cache/ms-playwright and are shared
across projects — this only fetches the node package. node_modules/ is gitignored.)
EOF
  exit 1
fi

# Is there anything to shoot? A dead sandbox produces thirty identical screenshots
# of a connection error, which is worse than an honest failure because it looks
# like output.
if [[ " $* " != *" --live "* && " $* " != *" --site "* ]]; then
  if ! curl -sf -o /dev/null --max-time 5 http://localhost:8080/ 2>/dev/null; then
    echo "✗ nothing is serving http://localhost:8080/"
    echo "  start the sandbox first:  ./scripts/local-wp.sh up"
    exit 1
  fi
fi

exec "${RUN[@]}" "$ROOT/scripts/shoot.mjs" "$@"
