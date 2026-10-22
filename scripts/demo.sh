#!/usr/bin/env bash
# Replay one service through the JSON API.
# The pass lags until `queue:work` runs. `php artisan projections:run to-serve`
# catches the same read model up inside this process.
set -euo pipefail

cd "$(dirname "$0")/.."

if [[ ! -f .env ]]; then
  cp .env.example .env
  php artisan key:generate --force
fi

mkdir -p database
touch database/database.sqlite
php artisan migrate:fresh --seed --force >/dev/null

PORT="${PORT:-8765}"
HOST=127.0.0.1
BASE="http://${HOST}:${PORT}"

php artisan serve --host="$HOST" --port="$PORT" >/tmp/restaurant-cqrs-serve.log 2>&1 &
SERVER=$!
cleanup() {
  kill "$SERVER" >/dev/null 2>&1 || true
  wait "$SERVER" 2>/dev/null || true
}
trap cleanup EXIT

ready=0
for _ in $(seq 1 50); do
  if curl -sf "${BASE}/up" >/dev/null; then
    ready=1
    break
  fi
  sleep 0.1
done

if [[ "$ready" -ne 1 ]]; then
  echo "The API did not start. See /tmp/restaurant-cqrs-serve.log" >&2
  exit 1
fi

call() {
  local method=$1
  local path=$2
  local body=${3:-}
  local expected=${4:-200}
  local if_match=${5:-}
  local tmp
  tmp=$(mktemp)

  if [[ -n "$body" ]]; then
    code=$(curl -sS -o "$tmp" -w '%{http_code}' -X "$method" \
      -H 'Accept: application/json' -H 'Content-Type: application/json' -H "If-Match: ${if_match}" \
      -d "$body" "${BASE}${path}")
  else
    code=$(curl -sS -o "$tmp" -w '%{http_code}' -X "$method" \
      -H 'Accept: application/json' "${BASE}${path}")
  fi

  cp "$tmp" /tmp/restaurant-cqrs-last.json
  rm -f "$tmp"
  echo "HTTP ${code} ${method} ${path}"
  php -r 'echo json_encode(json_decode(file_get_contents("/tmp/restaurant-cqrs-last.json"), true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;'

  if [[ "$code" != "$expected" ]]; then
    echo "Expected HTTP ${expected}." >&2
    exit 1
  fi
}

heading() {
  printf '\n# %s\n\n' "$1"
}

serve_the_pass() {
  echo
  echo "queue:work database --stop-when-empty"
  php artisan queue:work database --stop-when-empty --sleep=0 --max-jobs=50
  call GET /api/front-of-house/to-serve
  while IFS= read -r id; do
    [[ -z "$id" ]] && continue
    call POST "/api/front-of-house/to-serve/${id}/serve"
  done < <(php -r '$rows=json_decode(file_get_contents("/tmp/restaurant-cqrs-last.json"), true) ?: []; foreach ($rows as $row) { echo $row["id"], PHP_EOL; }')
}

heading "1. Seat two guests. The floor plan moves in the same transaction."
call POST /api/front-of-house/tables/table-4/guests '{"guestCount":2}'
VISIT=$(php -r 'echo json_decode(file_get_contents("/tmp/restaurant-cqrs-last.json"), true)["visitId"];')
call GET /api/front-of-house/floor-plan

heading "2. Place the first order."
call POST /api/front-of-house/tables/table-4/orders '{"lines":[{"menuItemId":"green-salad","quantity":1,"course":"starter"},{"menuItemId":"steak-frites","quantity":1,"course":"main"},{"menuItemId":"house-red","quantity":2,"course":"drink"}]}'

heading "3. A processor has opened a kitchen ticket. The screen has no prices."
call GET /api/kitchen/screen
TICKET=$(php -r '$t=json_decode(file_get_contents("/tmp/restaurant-cqrs-last.json"), true)[0]; echo $t["ticketId"];')
line_id() {
  DISH_NAME=$1 php -r 'foreach (json_decode(file_get_contents("/tmp/restaurant-cqrs-last.json"), true)[0]["lines"] as $line) { if ($line["dishName"] === getenv("DISH_NAME")) { echo $line["lineId"]; } }'
}
SALAD=$(line_id "Green salad")
STEAK=$(line_id "Steak frites")
WINE=$(line_id "House red")

heading "4. Fire the starter."
call POST "/api/kitchen/tickets/${TICKET}/fire" '{"course":"starter"}'
call POST "/api/kitchen/tickets/${TICKET}/lines/${SALAD}/ready"

heading "5. The pass is behind the kitchen until the queued projector runs."
call GET /api/front-of-house/to-serve
serve_the_pass

heading "Finish the first ticket: main and wine."
call POST "/api/kitchen/tickets/${TICKET}/fire" '{"course":"main"}'
call POST "/api/kitchen/tickets/${TICKET}/fire" '{"course":"drink"}'
call POST "/api/kitchen/tickets/${TICKET}/lines/${STEAK}/ready"
call POST "/api/kitchen/tickets/${TICKET}/lines/${WINE}/ready"
serve_the_pass

heading "6. Desserts. A second order opens a second ticket."
call POST /api/front-of-house/tables/table-4/orders '{"lines":[{"menuItemId":"creme-brulee","quantity":2,"course":"dessert"}]}'
call GET /api/kitchen/screen
DESSERT=$(php -r '$name="Creme brulee"; foreach (json_decode(file_get_contents("/tmp/restaurant-cqrs-last.json"), true) as $ticket) { foreach ($ticket["lines"] as $line) { if ($line["dishName"] === $name) { echo $ticket["ticketId"], " ", $line["lineId"]; } } }')
DESSERT_TICKET=${DESSERT%% *}
DESSERT_LINE=${DESSERT##* }
call POST "/api/kitchen/tickets/${DESSERT_TICKET}/fire" '{"course":"dessert"}'
call POST "/api/kitchen/tickets/${DESSERT_TICKET}/lines/${DESSERT_LINE}/ready"
serve_the_pass

heading "7. Mid-service, the steak is 86. The menu follows. A new order is refused."
call POST /api/kitchen/dishes/dish-steak/eighty-six
call GET /api/front-of-house/menu
call POST /api/front-of-house/tables/table-4/orders '{"lines":[{"menuItemId":"steak-frites","quantity":1,"course":"main"}]}' 422

heading "8. The bill is what was served. Split the payment, add a tip, free the table."
call POST /api/front-of-house/tables/table-4/bill
call GET "/api/billing/covers/${VISIT}/bill"
BILL=$(php -r 'echo json_decode(file_get_contents("/tmp/restaurant-cqrs-last.json"), true)["billId"];')
VERSION=$(php -r 'echo json_decode(file_get_contents("/tmp/restaurant-cqrs-last.json"), true)["version"];')
call POST "/api/billing/bills/${BILL}/payments" '{"amountCents":4000}' 200 "\"${VERSION}\""
call GET /api/front-of-house/floor-plan
call POST "/api/billing/bills/${BILL}/payments" '{"amountCents":2700,"tipCents":500}' 200 "\"${VERSION}\""
call GET "/api/billing/covers/${VISIT}/bill"
call GET /api/front-of-house/floor-plan
call POST "/api/billing/bills/${BILL}/payments" '{"amountCents":1}' 422 "\"${VERSION}\""

printf '\n# Service complete.\n'
