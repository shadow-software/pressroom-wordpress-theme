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
# Playwright uses the repo-local install (npm install) or a transient npx fetch —
# Playwright-managed Chromium in ~/.cache/ms-playwright, never system Google Chrome.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# Playwright is resolved from the repo-local install — never system Google Chrome.
# Browsers live in ~/.cache/ms-playwright and are shared across projects.
if [ -d "$ROOT/node_modules/playwright" ]; then
  :
elif command -v npx >/dev/null 2>&1; then
  # Transient fallback: fetch playwright for this run only (no node_modules write).
  exec npx --yes -p playwright node "$ROOT/scripts/shoot.mjs" "$@"
else
  cat <<'EOF'
Playwright is not installed. Install it locally:

  npm install
  npm run playwright:install

(The browsers themselves live in ~/.cache/ms-playwright and are shared
across projects — npm install only fetches the node package. node_modules/ is gitignored.)
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

exec node "$ROOT/scripts/shoot.mjs" "$@"
