# CQRS in Laravel

Companion code for a follow-up to [CQRS, separating the command from the query](https://joemugen.medium.com/cqrs-separating-the-command-from-the-query-2868b1fe373b).

Laravel post: coming soon

The metaphor stays the same. The menu is not the kitchen ticket. A guest reads a name, a price, and a list of allergens. The kitchen cooks a dish at a station for a number of minutes, and never sees the price. The till charges what actually left the pass. Same evening, three models, no shared class.

## Three levels

The base post separates three ways to keep a write model and a read model apart. This restaurant uses all three.

1. **Same transaction.** Seating guests updates the table and the floor-plan screen together. There is no bus. `SeatGuestsHandler` loads the table, lets it decide, saves it, and syncs the floor plan inside one unit of work.
2. **Asynchronous projector.** When a dish is ready, a queued job folds that fact into the "to serve" list and advances a checkpoint. Until a worker runs, the pass is behind the kitchen. The screen can lag. `php artisan projections:run to-serve` catches the same list up in process.
3. **Event stream.** A kitchen ticket is the stream. `KitchenTicketRepository` appends with the version it loaded. A conflicting version is refused. The kitchen screen folds the stream and can be rebuilt. It does not decide. The processor that opens a ticket is not a projection: it runs once and is not replayed.

A command is a message: intent and data, no rules. The aggregate decides. The handler loads, delegates, saves, and returns only an acknowledgement such as `{"tableOrderId": "…", "accepted": true}`. A query reads a read model and does not hydrate the aggregate.

## Bounded contexts

Each context names the evening differently.

| Evening | Front of house | Kitchen | Billing |
| --- | --- | --- | --- |
| Who is here | `Table` (`table-4`, label, guest count) | `partyRef` on a ticket | `coverRef` on a bill |
| What was asked for | `TableOrder` of `MenuItem`s (name, price, description, allergens, availability) | `KitchenTicket` of dishes (station, cooking time, course, no price) | `Bill` lines copied from dishes that were served |
| Out of stock | Menu item `available: false` | `Dish` declared 86 | — |
| Money | Price on the menu | Not present | VAT-inclusive totals, payments, tip |

They never call each other. A context publishes an integration event (a type string and a payload). Another context's processor reacts. The only shared module is `EventStore`, a technical log: append-only `events` (`stream_id`, `version`, `type`, `payload`, `occurred_at`, `position`) with an expected version, plus a `checkpoints` table.

Prices on the bill are tax-inclusive. Food eaten in is 10% (`gross * 10 / 110`) and alcohol is 20% (`gross * 20 / 120`).

## One service

1. `SeatGuests` — the floor plan updates in the same transaction.
2. `PlaceTableOrder` publishes `TableOrderPlaced`. The payload has no price.
3. A processor opens a `KitchenTicket` in the event store (version checked on every save).
4. The kitchen fires a course and marks a dish ready. The kitchen screen is a projection of that stream.
5. Front of house projects a queued "to serve" list. `ServeDish` publishes `DishServed`.
6. A second order on the same table (the desserts) opens a second ticket.
7. The kitchen declares the steak 86. The menu marks that item unavailable. A new order is refused. The steak already on a ticket can still be cooked.
8. `RequestBill` asks the till to prepare a `Bill` from served dishes only. `PayBill` may be split, and a tip does not reduce the balance. A paid bill is immutable. `BillPaid` frees the table, and the floor plan moves with it.

## Layout

```
app/Providers/BoundedContexts/
  EventStore/          the log, the checkpoints, the in-process wake-up
  FrontOfHouse/        dining room
  Kitchen/             tickets and the pass screen
  Billing/             the till
```

Each module is a bounded context with its own service provider, registered in `bootstrap/providers.php`. The provider loads that module's config, routes, and migrations, and binds ports to adapters. Pest discovers `tests/Unit` and `tests/Feature` inside the module.

Inside a context:

- `Domain` — aggregates, value objects, domain exceptions. No Laravel.
- `Application` — commands, handlers, ports.
- `Infrastructure` — SQL adapters, HTTP controllers, processors, projectors, queue jobs.

## Install

PHP 8.5 is pinned in `mise.toml` (8.5.11). Laravel 13.38 and Pest 5 run the project. SQLite is the database. There is no UI and no CQRS package.

```bash
mise install
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
```

`.env.example` sets `QUEUE_CONNECTION=database`, which is what makes the pass lag.

## Tests

```bash
php artisan test
```

Domain rules are pure Pest tests and do not boot Laravel. Each step of the service has an HTTP feature test. `tests/Feature/FullServiceTest.php` replays the evening from the welcome to the departure, including the moment the pass is empty until `queue:work` runs.

## Replay

```bash
bash scripts/demo.sh
```

The script migrates a fresh SQLite database, starts `php artisan serve`, and walks the JSON API with curl. After the starter is ready it prints an empty to-serve list, runs `php artisan queue:work database --stop-when-empty`, and only then serves the dish.

To catch the pass up without a worker:

```bash
php artisan projections:run to-serve
```

The kitchen screen projection runs in the request that appended the ticket event, and it can be rebuilt from the stream. The processors (open a ticket, mark the menu 86, record a served dish, prepare a bill, free a table) keep their own checkpoints and are not rebuilt.
