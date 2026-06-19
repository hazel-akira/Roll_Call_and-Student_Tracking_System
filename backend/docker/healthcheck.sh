#!/bin/sh
set -e

if [ -f /var/www/html/storage/.bootstrap-failed ]; then
  exit 1
fi

# Bootstrap still running — avoid marking the container unhealthy while migrations run.
if [ ! -f /var/www/html/storage/.bootstrap-complete ]; then
  exit 0
fi

curl -fsS --connect-timeout 2 --max-time 3 http://127.0.0.1/up >/dev/null
