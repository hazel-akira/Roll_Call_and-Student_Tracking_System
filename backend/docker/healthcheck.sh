#!/bin/sh
set -e

# Entrypoint creates this file immediately before exec; skip checks during setup.
if [ ! -f /tmp/app-ready ]; then
  exit 1
fi

curl -fsS http://127.0.0.1/up >/dev/null
