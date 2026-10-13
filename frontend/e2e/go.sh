#!/usr/bin/env bash
# Start two headless Edge instances (separate profiles = separate sessions), run journeys, stop Edge.
# Usage: go.sh <script.mjs> [journey ...]
cd /d/CN/Web/secondhand-marketplace || exit 1
EDGE="/c/Program Files (x86)/Microsoft/Edge/Application/msedge.exe"
for port in 9351 9352; do
  ("$EDGE" --headless=new --remote-debugging-port=$port --user-data-dir="$(cygpath -w "$PWD/frontend/e2e/profile-$port")" --no-first-run --disable-gpu --autoplay-policy=no-user-gesture-required about:blank >/dev/null 2>&1 &)
done
for i in $(seq 1 15); do curl -s http://127.0.0.1:9351/json/version >/dev/null && curl -s http://127.0.0.1:9352/json/version >/dev/null && break; timeout 1 ping -n 2 127.0.0.1 >/dev/null; done
script=$1; shift
MSYS2_ARG_CONV_EXCL="*" timeout 560 node "frontend/e2e/$script" "$@"
powershell -NoProfile -Command "Get-CimInstance Win32_Process -Filter \"name='msedge.exe'\" | Where-Object { \$_.CommandLine -like '*935[12]*' } | ForEach-Object { Stop-Process -Id \$_.ProcessId -Force -ErrorAction SilentlyContinue }" >/dev/null 2>&1
