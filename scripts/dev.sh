#!/usr/bin/env bash
# Start the stack in one terminal, one prefixed log stream per process. Ctrl+C stops everything.
#
#   composer dev        worker mode: the API and one worker per context (kitchen:consume, ...)
#   composer dev:sync   sync mode:   the API and the queue worker; consumers run in the request
#   composer workers    worker mode: the workers only, when the API runs elsewhere
#
# The workers are the `*:consume` commands artisan lists, so a new context needs no change here.
# Add --dry-run to print what would start. Needs bash 4.3 or newer for `wait -n`.
set -euo pipefail

cd "$(dirname "$0")/.."

MODE="${1:-worker}"
DRY_RUN=0
[[ "${2:-}" == "--dry-run" ]] && DRY_RUN=1

PHP="${PHP_BINARY:-php}"
HOST="${HOST:-127.0.0.1}"
PORT="${PORT:-8000}"

usage() {
  echo "Usage: scripts/dev.sh [worker|sync|workers] [--dry-run]" >&2
  exit 2
}

case "$MODE" in
  worker | sync | workers) ;;
  *) usage ;;
esac

names=()
commands=()
add() {
  names+=("$1")
  commands+=("$2")
}

add_workers() {
  local command
  while read -r command; do
    [[ -n "$command" ]] && add "${command%%:*}" "$PHP artisan $command"
  done < <("$PHP" artisan list --raw | awk '/:consume( |$)/ { print $1 }')
}

add_logs() {
  if "$PHP" artisan list --raw | grep -q '^pail '; then
    add logs "$PHP artisan pail --timeout=0"
  fi
}

case "$MODE" in
  worker)
    CONSUMPTION=worker
    add serve "$PHP artisan serve --host=$HOST --port=$PORT"
    add_workers
    add_logs
    ;;
  workers)
    CONSUMPTION=worker
    add_workers
    ;;
  sync)
    CONSUMPTION=sync
    add serve "$PHP artisan serve --host=$HOST --port=$PORT"
    add queue "$PHP artisan queue:listen --tries=1 --timeout=0"
    add_logs
    ;;
esac

if [[ "$DRY_RUN" -eq 1 ]]; then
  echo "EVENT_CONSUMPTION=$CONSUMPTION"
  for i in "${!names[@]}"; do
    echo "[${names[$i]}] ${commands[$i]}"
  done
  exit 0
fi

if [[ ! -f .env ]]; then
  cp .env.example .env
  "$PHP" artisan key:generate --force
fi

mkdir -p database
touch database/database.sqlite
"$PHP" artisan migrate --force

# An exported variable wins over .env, so the API and the workers agree on who consumes events.
export EVENT_CONSUMPTION="$CONSUMPTION"

# Job control gives each process its own group, so one signal reaches a process and everything it started.
set -m
pids=()

stop_all() {
  trap - EXIT INT TERM
  local pid
  for pid in "${pids[@]}"; do
    kill -TERM -- "-$pid" 2>/dev/null || true
  done
  wait 2>/dev/null || true
}
trap stop_all EXIT
trap 'echo "Stopping the stack." >&2; exit 0' INT TERM

for i in "${!names[@]}"; do
  (
    eval "${commands[$i]}" 2>&1 |
      awk -v prefix="[${names[$i]}]" '{ print prefix " " $0; fflush() }'
  ) &
  pids+=("$!")
done

echo "Stack running in $CONSUMPTION mode: ${names[*]}. Ctrl+C to stop."

# One process stopping, a worker refused by its lock or the API crashing, takes the stack down.
status=0
wait -n || status=$?
echo "A process of the stack stopped (exit $status). Stopping the others." >&2
exit "$status"
