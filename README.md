# CQRS, Event Sourcing, DDD and hexagonal architecture in Laravel

One restaurant, used as the example of several posts. Each post isolates one idea; the code keeps all of them together.

## Posts based on this repository

| Post | Subject | Why this repository |
| --- | --- | --- |
| [CQRS, separating the command from the query](https://joemugen.medium.com/cqrs-separating-the-command-from-the-query-2868b1fe373b) | CQRS | The base post. This code is its Laravel follow-up: the three levels below, with a menu, a kitchen ticket and a bill that share no class. |
| Laravel post: coming soon | CQRS in Laravel | Buses, middleware, projections and workers in a real framework. |

A post that needs its own variation gets its own branch.

The words of the restaurant (86, the pass, a cover...) are defined in [GLOSSARY.md](GLOSSARY.md): the code speaks them.

The metaphor stays the same. The menu is not the kitchen ticket. A guest reads a name, a price, and a list of allergens. The kitchen cooks a dish at a station for a number of minutes, and never sees the price. The till charges what actually left the pass. Same evening, three models, no shared class.

## Three levels

The base post separates three ways to keep a write model and a read model apart. This restaurant uses all three.

1. **Same transaction.** Seating guests updates the table and the floor-plan screen together. `SeatGuestsHandler` loads the table, lets it decide, saves it, and syncs the floor plan. The command bus wraps the handler in one transaction, so the two writes commit together.
2. **Asynchronous projector.** When a dish is ready, a queued job folds that fact into the "to serve" list and advances a checkpoint, and takes a dish off it when it is served. Until a worker runs, the list is behind the kitchen. The list can lag and can be rebuilt from the log; it decides nothing. `php artisan projections:run to-serve` catches the same list up in process.
3. **Event stream.** A kitchen ticket is the stream. `KitchenTicketRepository` appends with the version it loaded. A conflicting version is refused. The kitchen screen folds the stream and can be rebuilt. It does not decide. The processor that opens a ticket is not a projection: it runs once and is not replayed.

A command is a message: intent and data, no rules. The aggregate decides. The handler loads, delegates, saves, and returns only a typed acknowledgement such as `TableOrderPlaced`, which serializes to `{"tableOrderId": "…", "accepted": true}`. A query reads a read model and does not hydrate the aggregate.

## Bounded contexts

Each context names the evening differently.

| Evening | Front of house | Kitchen | Billing |
| --- | --- | --- | --- |
| Who is here | `Table` (`table-4`, label, guest count) and the visit of the guests who sit there | `partyRef` on a ticket | `coverRef` on a bill (the visit) |
| What was asked for | `TableOrder` of lines copied from `MenuItem`s (name, price at the time of the order, description, allergens, availability) | `KitchenTicket` of dishes (station, cooking time, course, no price) | `Bill` lines built from the dishes that were served, at the price of their order line |
| Out of stock | Menu item `available: false` | `Dish` declared 86 | — |
| Money | Price on the menu, kept on the line once ordered | Not present | VAT-inclusive totals, payments, tip |

They never call each other, and they do not share a class for the same concept. A context publishes an integration event (a type string and a payload). After the writer's transaction commits, each processor and projector runs in its own transaction and advances its own checkpoint. A failure in the kitchen cannot roll back an order. The shared modules are `SharedKernel` (contracts, buses, value types) and `EventStore`, a technical log: append-only `events` (`stream_id`, `version`, `type`, `payload`, `occurred_at`, `position`) with an expected version, plus a `checkpoints` table.

The contexts find a dish of another by its **name**, which is unique in the menu and in the kitchen: on a short menu the name of a dish is its business key (a long menu would carry a code). A line of an order keeps the price of the menu at the time of the order, and that is the price the till charges.

What the guests ordered, were served and owe belongs to their **visit**, never to the table: a table sees several in an evening. The till knows a visit as a cover, and a bill counts what was served to that visit, and asking for it freezes nothing: a dish served afterwards goes on the bill that is still open, which moves to its next version, as the guests see their note grow. The next guests are another visit, so another bill. A paid bill is immutable: a dish that comes out after it is written off, and the till says the loss as a fact (`billing.dish_written_off`) instead of leaving a line on no bill.

Prices on the bill are tax-inclusive. Food eaten in is 10% (`gross * 10 / 110`) and alcohol is 20% (`gross * 20 / 120`).

## One service

1. `SeatGuests` — the floor plan updates in the same transaction.
2. `PlaceTableOrder` publishes `TableOrderPlaced`. The payload has no price.
3. A processor opens a `KitchenTicket` in the event store (version checked on every save).
4. The kitchen fires a course and marks a dish ready. The kitchen screen is a projection of that stream.
5. A processor puts each ready dish at the pass, the write model where the room decides what was served, and a queued projector keeps a "to serve" list of it. `ServeDish` decides on the pass, never on the list, and publishes `DishServed`. It refuses (409) to serve guests whose visit is closed: the payment closes it.
6. A second order on the same table (the desserts) opens a second ticket.
7. The kitchen declares the steak 86. The menu marks that item unavailable. A new order is refused. The steak already on a ticket can still be cooked.
8. `RequestBill` asks the till to prepare a `Bill` from served dishes only. `PayBill` may be split, and a tip does not reduce the balance. A paid bill is immutable. `BillPaid` frees the table, and the floor plan moves with it.

## Layout

```
app/Providers/
  Modules/               technical machinery the contexts share, importing none of them
    SharedKernel/        the contracts, buses and value types every context implements
    EventStore/          the log, the checkpoints, the in-process wake-up
  BoundedContexts/
    FrontOfHouse/        dining room
    Kitchen/             tickets and the pass screen
    Billing/             the till
```

A bounded context has its own service provider, registered in `bootstrap/providers.php`. The provider loads the context's config, routes, and migrations, and binds ports to adapters. Pest discovers `tests/Unit` and `tests/Feature` inside each context. `SharedKernel` and `EventStore` are modules, not bounded contexts: they model no part of the restaurant, they are technical machinery the contexts share, so they live beside them and import none of them.

Inside a context:

- `Domain` — aggregates (`AggregateRoot`, or `EventSourcedAggregate`), value objects, domain exceptions. No Laravel.
- `Application` — ports at the root. `Command` holds the commands (`Command`), their handlers (`CommandHandler`, declared with `#[AsCommandHandler]`) and, in `Command/Response`, what they answer (`CommandResponse`). `Query` holds the queries (`Query`) and, in `Query/Response`, the views they return (`QueryResponse`).
- `Infrastructure` — SQL adapters, HTTP controllers, query handlers in `Query` (`QueryHandler`), processors (`Processor`), projectors (`Projector`, `RebuildableProjector`), queue jobs.

The contracts live in `SharedKernel`. Identifiers and names are types, not strings: a `TableId` is not a `BillId`, and each aggregate `id()` returns an `AggregateId`. They cast to string and serialize to JSON. Raw strings stay at the edges: SQL columns, event payloads and HTTP input.

Ids the system generates are namespaced UUIDs (version 5): the namespace is derived per aggregate type, so a bill id is never an order id, and the name comes from the business fact. A visit id is the table plus how many visits it has seated, decided by the table and not by the client: no key, purged or replayed, can name a visit twice. A bill id is the cover plus the position of the event that asked for it, so replaying that event finds the same bill. An order id is the table plus the `Idempotency-Key` header of `POST /tables/{tableId}/orders`: sending the same key again returns the same `tableOrderId` and places nothing twice. Without the header every request gets a fresh name. A kitchen ticket takes the id of its order, so its stream is `kitchen-ticket-<uuid>`.

A Pest test in `SharedKernel/tests/Unit/ContractsTest.php` walks every context and fails when a class misses its contract.

## Buses

Controllers (one single-action class per use case, such as `SeatGuestsController` or `ShowBillController`) and processors do not call a handler. They send a message on their context bus: `FrontOfHouseCommandBus::dispatch(new SeatGuests(...))`, `FrontOfHouseQueryBus::ask(new ListFloorPlan)`. Each bus extends `MiddlewareCommandBus` or `MiddlewareQueryBus` from the SharedKernel and lists its handlers.

A handler declares what it handles and takes that type, so no `assert` is needed:

```php
#[AsCommandHandler(SeatGuests::class)]
final readonly class SeatGuestsHandler implements CommandHandler
{
    public function __invoke(SeatGuests $command): TableSeated
```

A bus does not list its handlers. `ContextDiscovery` scans `Application/Command` and `Infrastructure/Query` of the context the bus lives in. `HandlerMap` checks each attribute against the signature (parameter, response type, duplicates) when a bus is built, and a feature test checks that every handler of a context is found.

`php artisan contexts:cache` writes the discovery to `bootstrap/cache/contexts.php` and `contexts:clear` removes it. Both run with `php artisan optimize` and `optimize:clear`. Without the file, the directories are scanned on demand.

Middleware wraps the handler, outermost first:

| Stack | Middleware |
| --- | --- |
| Commands | `LoggingCommandMiddleware`, `MetricsCommandMiddleware`, `AuthorizationCommandMiddleware`, what the context puts outside the transaction, `TransactionMiddleware`, `IdempotencyMiddleware` |
| Queries | `LoggingQueryMiddleware`, `MetricsQueryMiddleware`, `AuthorizationQueryMiddleware` |
| Kitchen and front-of-house commands | adds `RetryOnConflict` outside the transaction, so a ticket or an order changed by someone else is read and decided again, three attempts, then a 409 |

`#[Authorize('ability')]` on a command or a query is checked against the Gate before the handler runs. A message without it is not guarded. A command that implements `IdempotentCommand` carries an `IdempotencyKey` (the `Idempotency-Key` header). `IdempotencyMiddleware` runs inside the transaction: the first request claims the key in `idempotency_keys`, runs the handler and stores the response, so the claim, the writes and the response commit together. The same key with the same payload returns the stored response (as a `ReplayedResponse`, the handler is not run); with another payload it is refused with a 422. A concurrent request waits for the claim, then replays it. A command without a key is not guarded. A context adds middleware by overriding `outsideTransaction()` (commands) or `middleware()`.

## Consuming events

Processors and projectors implement `EventConsumer::consume()`: read the log from a checkpoint, react, return how many events were handled. What wakes them depends on `EVENT_CONSUMPTION`.

| Mode | Who calls `consume()` |
| --- | --- |
| `sync` (default) | The request that appended the fact, right after its transaction commits. Each consumer has its own transaction, and a failure is reported without rolling the writer back. The "to serve" projector is marked `#[RunsOnQueue]`: it dispatches its job and waits for `queue:work`. |
| `worker` | Nothing in the request. One long-running command per context reads the log and waits for more. |

```bash
php artisan kitchen:consume                    # standby, one pass per second when idle
php artisan front-of-house:consume --sleep=2
php artisan billing:consume --stop-when-empty  # drain and exit
php artisan kitchen:consume --max-time=3600    # exit after an hour, let the supervisor restart it
```

The worker runs every consumer of its context on each pass, each in its own transaction. A failing consumer is reported and retried on the next pass, and the others keep going. On SIGTERM or SIGINT it finishes its pass and exits. The consumers are discovered in `Infrastructure/Processors` and `Infrastructure/Projection`, so a new one needs no registration.

### An idle worker costs the database nothing

Before it opens a transaction or takes a lock, a worker asks each consumer `hasWork()`: a read that checks whether an event sits past the consumer's checkpoint (`EventStore::hasEventsAfter()`). With nothing to do, a pass makes no write and opens no transaction, and the worker renews its presence lock every third of its lifetime instead of on every pass. This matters with SQLite, where one writer at a time is allowed and the API and the workers share the file. The connection also waits for a lock (`busy_timeout`, 5 s), uses WAL so readers go on during a write, and takes the write lock when a transaction begins (`transaction_mode: IMMEDIATE`), all tunable through `DB_BUSY_TIMEOUT`, `DB_JOURNAL_MODE`, `DB_SYNCHRONOUS` and `DB_TRANSACTION_MODE`.

A consumer that depends on another one says so in code. `PrepareBillProcessor` reads its events, then brings the till's ledger of served dishes up to date, so a bill request never overtakes the dishes served before it, whatever order the consumers wake in.

### Eventual consistency: judge a fact where it happened

With a worker, a consumer may read a fact long after it was appended, so it must not judge it against the state of the world now. The kitchen shows it: table 7 orders a steak, the kitchen declares it 86, and only then does the kitchen worker read the order. The steak was available when the order was placed, so the ticket opens.

The kitchen records the log position of the 86 (`kitchen_dishes.eighty_sixed_position`, which `ContractPublisher::publish()` and `EventStore::append()` now return), and the domain judges with it: `Dish::isAvailableAt()` takes the position of the order, so a dish is refused only when it was declared 86 at a position before the order's. The log position is the clock all consumers share.

### A partial order is revised, not cancelled

The dining room accepts an order from its own menu, which can be a moment behind the kitchen. When some dishes are 86, that is a business outcome and not an error to retry. `OpenKitchenTicketProcessor` hands the lines to `ReceiveOrder`; the domain service `OrderAssessment` sets aside what cannot be cooked, the ticket opens with the rest at once, and `kitchen.lines_unavailable` tells the others, which is the fact the assessment returns: the lines, the reason, and `orderPosition`, the position in the log of the order it judged. With nothing available there is no ticket, only that fact.

`OrderFulfilmentProcessor` keeps an `OrderFulfilment` for each order. `GET /api/front-of-house/orders/{tableOrderId}` shows its status and its `unavailableLines`.

```
placed -> awaiting-kitchen --ticket opened / dishes added--> confirmed
              ^                                                  |
              | ReviseOrder                     lines_unavailable|
              |                                                  v
              +------------------------------------------ awaiting-revision --CancelOrder--> cancelled

     any order that is not over --billing.bill_paid--> closed
```

The waiter asks the guests, then `POST /orders/{id}/revisions` adds replacement lines and abandons the unavailable ones. `ReviseOrder` writes the order and nothing else (one aggregate per transaction): the saga learns of the revision from its fact, `front_of_house.table_order_revised`, and a second revision is refused until it has seen the first. The fact makes the kitchen add the cookable lines to the same ticket (`kitchen.dishes_added`, which moves the ticket version), or open it if there was none. A replacement can be 86 too, and the loop starts again. If the guests give up, `POST /orders/{id}/cancel` abandons only the lines still unavailable: what is on the ticket is still cooked.

The payment closes the visit. `billing.bill_paid` closes every order of that visit that is not over, and each one says it with `front_of_house.table_order_closed`: the kitchen cancels what its ticket still has to cook (a ticket that is finished is left alone), the list to serve drops what waited at the pass, and `ServeDish` refuses the visit (409). A bill is paid before a revision: the order is closed, and `ReviseOrder` is refused with a 412 (`The revision of the order is obsolete: the guests already paid and left.`). The payment names the visit that paid, so it closes the orders of that visit and no other, and frees the table only if that visit is still the one sitting there: a payment heard late, or twice, says nothing of the guests who sit there now. The position of the order keeps a late answer of the kitchen from undoing a revision made since. The table is freed by `billing.bill_paid` alone, so nobody leaves without paying.

### One worker per stream

The event store does not partition the log, so its limit today is **one worker per stream**: one for each context, running all its consumers. A cache lock, `event-worker:<context>`, enforces it. A second `kitchen:consume` is refused and exits with code 1, and so is any worker whose cache store cannot share a lock between processes (`array`, `null`) outside the tests. If a worker dies, the lock expires by itself after `EVENT_CONSUMER_LOCK_SECONDS` (120), which must exceed the longest pass of a consumer. Use `database` or `redis` as `CACHE_STORE`. `php artisan cache:clear` frees the lock by hand.

In `sync` mode the process that appended the fact consumes it, one at a time: fine for `artisan serve`, the tests and one queue worker, not for a server that handles requests in parallel, which must use worker mode.

### What the log guarantees

- **Append only.** `events` only receives inserts, a mistake is answered by a new fact. A test records every statement of a whole service, in both modes.
- **In order, per consumer.** A consumer reads past its checkpoint by position and writes the checkpoint with its effects in one transaction, so a failed pass starts again at the same place. With SQLite it skips nothing, see below.
- **At least once.** After a failure a consumer may see an event twice, so each one is idempotent.
- **No order between consumers.** A consumer that needs another to be up to date says so in code, as `PrepareBillProcessor` does for the ledger of served dishes.

### What holds because of SQLite

Two guarantees come from the database, not from the code, and hold with SQLite only (one writer at a time, `transaction_mode: IMMEDIATE`).

- **The position as a checkpoint.** `events.position` is auto-incremented. With several writers, a transaction can commit after another that took a higher position, and a consumer that already passed it skips the event for good. A server database needs the appends serialized (an advisory lock around the append) or a consumer that waits for the gaps.
- **Aggregates saved as their state.** `Bill`, `Table` and `OrderFulfilment` have no version and no lock: two `PayBill` that read the same state are serialized only because the writers are. A server database needs a version column checked on save, or a lock on the row. The ticket of the kitchen, rebuilt from its events, is safe either way: its append checks the expected version, and so does the save of a `TableOrder`, which writes only if its revisions are still those it read: two `ReviseOrder` cannot both go through, and the one that lost reads the order again and is told that a revision already waits.

## A fact of the domain is not a contract

An aggregate records typed facts (`DishesAdded`, `TableOrderPlaced`, `BillPaid`: classes of `Domain/Event`, a `TypedDomainEvent`), never an array. The ticket of the kitchen, which is rebuilt from its events, stores them in its own stream under a name the kitchen owns (`ticket.dishes_added`) and in a format it may change. The other aggregates are saved as their state and `RecordsEvents` keeps the facts until the handler releases them.

What the other contexts read is a contract, a class of `Application/Integration`: `DishesAddedV1` says `#[PublishedAs('kitchen.dishes_added', version: 1)]` and `#[Translates(DishesAdded::class)]`, is built by `from()`, and keeps back what the others do not need. A handler never builds an integration event: it hands the released facts to the `ContractPublisher` port, which translates each one by convention and tells the contract on the bus, in the transaction of the command, and returns where it sits in the log. The version sits in the envelope (`events.contract_version`), not in the payload.

A typed fact is `#[Internal]` (`DishFired`, `TicketCompleted`) or translated, and a Pest test fails on one that is neither: whether others will read a fact is decided when it is written. Another test fails on a context that builds an `IntegrationEvent` or holds the bus itself. The reading side has its fuse too. A context declares what it reads of another one in a class of `Infrastructure/Consumed` (`#[Consumes('kitchen.dish_ready', version: 1)]`), in its own words and with its own types, and keeps of the payload only what it needs. A processor asks the `ConsumedMessages` port what the event means to it (`$message instanceof KitchenDishReady`), and no context imports the class of another. A Pest test fails on a message that reads a contract nobody publishes, or under another version.

Contracts, handlers, processors and projectors are found by namespace, not by path: `config/contexts.php` names the namespace of the contexts and the sub-namespace of each layer, Composer's PSR-4 map gives the directories, and `contexts:cache` keeps the result.

## Stale commands

Each ticket on the kitchen screen has a `version`: the number of facts in its stream. A client sends it back as `If-Match` on `fire` and `ready`, and the response carries the new version as an `ETag`. If the ticket moved on, the command is refused with a 412 and nothing changes, so the client reads again and decides again. The header is optional, and a refusal is never replayed by the bus, unlike a write conflict between two writers.

A payment is a stale command of the same kind, and its header is not optional: `POST /bills/{billId}/payments` pays the note the guests saw, so it carries its version as `If-Match` (the `ETag` of `GET /covers/{visitId}/bill`). If a dish joined the bill since, the payment is refused with a 412 and the note is presented again; without the header it is refused with a 428.

## Tracing a request

Every request carries a correlation id. `CorrelationIdMiddleware`, first in the `api` group (see `bootstrap/app.php`), reads `X-Correlation-Id` (or `X-Request-Id`) when it is a short token of letters, digits, dots, dashes and underscores, makes a UUID otherwise, and returns it in the `X-Correlation-Id` header of the response, a refusal included. Quote it when something goes wrong.

The id lives in the Laravel Context, so it follows the work after the request:

- every log line carries it, and the bus logging middleware starts one when work begins outside a request;
- every event appended is stored with it (`events.correlation_id`);
- a worker resumes the id of the event it reacts to before it dispatches a command, so what it appends carries the id of the request that started the chain, and two requests handled in one pass stay apart;
- a queued job gets it from the Context, and a consumer puts the previous id back when it ends.

The correlation id names the whole chain. The causation id (`events.causation_id`, `StoredEvent::$causationId`) names the parent link: the message that directly caused an event. `LoggingCommandMiddleware` gives each command an id, logged as `command_id` with `caused_by`, the id of what caused the command. An event a handler appends points to its command. A command a processor dispatches points to the position of the event it reacts to, so the chain order placed, ticket opened, order confirmed can be walked link by link. A fact appended outside any message has no cause.

```bash
curl -i -H 'X-Correlation-Id: checkout-42' -X POST localhost:8000/api/front-of-house/tables/table-4/orders ...
sqlite3 database/database.sqlite "select position, type, causation_id from events where correlation_id = 'checkout-42'"
```

## Metrics

Both buses count every message by type and outcome (`success`, `refused`, `conflict`, `precondition_failed`, `failure`) with the sum and the slowest duration. `MetricsCommandMiddleware` wraps the retry, so a retried command counts once, as its caller sees it. `ConsumerWorker` does the same per consumer for each pass that had something to read, and counts the events it handled. Lag, the positions a checkpoint has left to read, is computed when you ask, so a stopped worker shows it growing. A metric that cannot be written (a locked database) is reported and never fails the message or the worker.

The `MetricsRecorder` port has a database adapter, not a cache one: a single upsert adds the count and the sum and keeps the max atomically, which a cache increment cannot do for the max. Tests bind `InMemoryMetricsRecorder`.

```bash
php artisan metrics:show
curl localhost:8000/metrics    # Prometheus text, unauthenticated: keep it on the private network
```

## Run the stack

One terminal, one prefixed log stream per process, Ctrl+C stops everything. The composer scripts call the `Makefile`, and `make` alone lists its targets.

```bash
composer dev        # make dev:      the API and one worker per context (worker mode)
composer dev:sync   # make dev-sync: the API and the queue worker, consumers run in the request
composer workers    # make workers:  the workers only, when the API runs elsewhere
make logs           # php artisan pail, in a second terminal
make consume-kitchen  # one worker, for one context
```

`make dev` runs `php artisan serve` (`HOST` and `PORT` change 127.0.0.1:8000) and a `*:consume` worker for every bounded context, as `make -j` targets. It sets `EVENT_CONSUMPTION=worker` for all of them, so the API and the workers agree on who consumes events. `make dev-sync` starts the API and `queue:listen` instead, and consumers run in the request. The workers are the `*:consume` commands that `artisan list` shows: a new context needs no change in the Makefile.

Each target first creates `.env` and the SQLite file if they are missing and runs the migrations. A process that stops, a worker refused by its lock for instance, does not stop the others: read its error in its stream and press Ctrl+C. `make -n dev` prints what would run. `PHP` picks the php binary, which composer sets for you.

## Install

PHP 8.5 is pinned in `mise.toml` (8.5.11). Laravel 13.38 and Pest 5 run the project. SQLite is the database. There is no UI and no CQRS package.

```bash
mise install
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
```

`.env.example` sets `QUEUE_CONNECTION=database`, which is what makes the pass lag in sync mode, and `CACHE_STORE=database`, which the consumer locks need. `EVENT_CONSUMPTION` (`sync` or `worker`) picks who consumes events.

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

The kitchen screen catches up after the order's transaction commits, before the HTTP response returns, and it can be rebuilt from the stream. The list to serve can be rebuilt too, without bringing back a dish already served. The processors (receive an order, put a dish at the pass, mark the menu 86, record a served dish, prepare a bill, free a table) keep their own checkpoints and are not rebuilt. Only the list waits for a worker.

## Where to read

| Step | Lines |
| --- | --- |
| 1. Seat guests and the floor plan | `app/Providers/BoundedContexts/FrontOfHouse/Application/Command/SeatGuestsHandler.php#L27-L35` |
| 2. Place a table order | `app/Providers/BoundedContexts/FrontOfHouse/Application/Command/PlaceTableOrderHandler.php#L31-L48` |
| 3. Open a kitchen ticket | `app/Providers/BoundedContexts/Kitchen/Infrastructure/Processors/OpenKitchenTicketProcessor.php#L37-L58`, `app/Providers/BoundedContexts/Kitchen/Application/Command/ReceiveOrderHandler.php#L33-L52`, `app/Providers/BoundedContexts/Kitchen/Domain/KitchenTicket.php#L55-L65` |
| 4. Fire, ready, kitchen screen | `app/Providers/BoundedContexts/Kitchen/Domain/KitchenTicket.php#L104-L153`, `app/Providers/BoundedContexts/Kitchen/Infrastructure/Projection/KitchenScreenProjector.php#L68-L117` |
| 5. Queued pass, then serve | `app/Providers/BoundedContexts/FrontOfHouse/Infrastructure/Projection/ToServeProjector.php#L38-L71`, `app/Providers/BoundedContexts/FrontOfHouse/Infrastructure/Projection/ProjectToServeList.php#L10-L18`, `app/Providers/BoundedContexts/FrontOfHouse/Application/Command/ServeDishHandler.php#L27-L36` |
| 6. Second order | `tests/Feature/FullServiceTest.php#L56-L70` |
| 7. 86 the menu | `app/Providers/BoundedContexts/Kitchen/Domain/Dish.php#L35-L43`, `app/Providers/BoundedContexts/Kitchen/Application/Command/DeclareDishEightySixedHandler.php#L24-L32`, `app/Providers/BoundedContexts/FrontOfHouse/Infrastructure/Processors/MarkMenuItemUnavailableProcessor.php#L35-L58` |
| 8. Bill, pay, free the table | `app/Providers/BoundedContexts/FrontOfHouse/Application/Command/RequestBillHandler.php#L24-L31`, `app/Providers/BoundedContexts/Billing/Infrastructure/Processors/RecordServedDishes.php#L35-L56`, `app/Providers/BoundedContexts/Billing/Application/Command/PrepareBillHandler.php#L27-L49`, `app/Providers/BoundedContexts/Billing/Domain/Bill.php#L48-L72`, `app/Providers/BoundedContexts/Billing/Application/Command/PayBillHandler.php#L24-L33`, `app/Providers/BoundedContexts/FrontOfHouse/Application/Command/FreeTableHandler.php#L27-L35` |
| 9. Revise a partial order | `app/Providers/BoundedContexts/FrontOfHouse/Application/Command/ReviseOrderHandler.php#L35-L49`, `app/Providers/BoundedContexts/Kitchen/Domain/OrderAssessment.php#L20-L41`, `app/Providers/BoundedContexts/Kitchen/Domain/Dish.php#L35-L43`, `app/Providers/BoundedContexts/FrontOfHouse/Domain/OrderFulfilment.php#L70-L145`, `app/Providers/BoundedContexts/Kitchen/Domain/KitchenTicket.php#L86-L102`, `app/Providers/BoundedContexts/FrontOfHouse/Infrastructure/DatabaseTableOrderRepository.php#L69-L98` |
| 10. A fact of the domain, a contract for the others | `app/Providers/BoundedContexts/Kitchen/Domain/Event/DishesAdded.php#L14-L46`, `app/Providers/BoundedContexts/Kitchen/Application/Integration/DishesAddedV1.php#L18-L55`, `app/Providers/Modules/EventStore/Infrastructure/ConventionContractPublisher.php#L30-L41`, `app/Providers/BoundedContexts/Kitchen/Infrastructure/EventStoreKitchenTicketRepository.php#L53-L71` |
| Level 1 | `SeatGuestsHandler.php#L27-L35` and `FreeTableHandler.php#L27-L35` |
| Level 2 | `ToServeProjector.php#L38-L71` and `ProjectToServeList.php#L10-L18` |
| Level 3 | `app/Providers/Modules/EventStore/Infrastructure/DatabaseEventStore.php#L23-L74`, `app/Providers/BoundedContexts/Kitchen/Infrastructure/EventStoreKitchenTicketRepository.php#L53-L71`, `app/Providers/Modules/EventStore/Infrastructure/InProcessAfterAppend.php#L53-L60` |
