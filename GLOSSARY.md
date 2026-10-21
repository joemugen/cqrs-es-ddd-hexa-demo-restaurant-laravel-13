# Glossary

The words of the restaurant, as the code speaks them. This is the ubiquitous language: a word here is a class, a method or a status there, and a word that is not here does not enter the model. English is the language of the code; the French term helps whoever talks to the people of the trade.

The same word does not always mean the same thing in two contexts, and that is on purpose (see [Bounded contexts](README.md#bounded-contexts)). Each entry says where it lives.

## The room

| Term | FR | Meaning | In the code |
| --- | --- | --- | --- |
| **Service** | service | One evening, from the welcome of the guests to their departure. | `tests/Feature/FullServiceTest.php` |
| **Table** | table | A place in the dining room. It is free, or occupied by guests. | `FrontOfHouse` `Table`, `TableStatus` |
| **Guests** | convives, clients | The people sitting at a table. | `Table::seat()`, `guestCount` |
| **Seat** | installer | Welcome guests at a free table. A table that already has guests cannot be seated. | `SeatGuests` |
| **Floor plan** | plan de salle | What the room sees of its tables: label, guests, free or occupied. | `FloorPlan`, `GET /floor-plan` |
| **Menu**, **menu item** | carte, plat à la carte | What the room offers, with its price and allergens. A menu item is available or not. | `Menu`, `MenuItem` |
| **Order** | commande | What a table asks for, once. A table can place several (the desserts come later). | `TableOrder`, `PlaceTableOrder` |
| **Line** | ligne | One dish of an order, with its quantity and its course. The kitchen never sees its price. | `OrderLine` (room), `TicketLine` (kitchen) |
| **Course** | service (entrée, plat…) | When in the meal a dish comes: `starter`, `main`, `dessert`, `drink`. | `Course` |
| **Allergens** | allergènes | What a dish contains that some guests cannot eat. They travel with the line to the kitchen. | `allergens` |
| **Waiter** | serveur | The person who talks to the guests. The system has no waiter object: the waiter acts through the HTTP routes. | `ReviseOrder`, `CancelOrder` |

## The kitchen

| Term | FR | Meaning | In the code |
| --- | --- | --- | --- |
| **Dish** | plat (en cuisine) | What the kitchen cooks, at a station, for some minutes, with no price. The menu item of the room and the dish of the kitchen are two models of the same plate. | `Kitchen` `Dish` |
| **Ticket** | bon de commande, ticket | What the kitchen works from for one order: its dishes, and where each stands. It takes the id of its order. | `KitchenTicket` |
| **Station** | poste | Where a dish is cooked: `grill`, `garnish`, `pastry`, `bar`. | `station` |
| **Cooking time** | temps de cuisson | How long a dish takes at its station. | `cookingMinutes` |
| **Fire** | lancer | Tell the stations to start cooking a whole course of the ticket. | `FireDishes`, `KitchenTicket::fireCourse()` |
| **Ready** | prêt | A fired dish is cooked and waits at the pass. | `MarkDishReady`, `DishReady` |
| **Kitchen screen** | écran cuisine | What the cooks see: the tickets and the state of each dish. A reading, rebuilt from the tickets. | `KitchenScreenProjector` |
| **86** (verb or adjective) | être en rupture, « 86 » | The kitchen has run out of a dish: it is *86*, or the cook *86es* it. See the entry below. | `Dish::eightySix()`, `DishEightySixed` |
| **Unavailable line** | ligne indisponible | A line of an order that the kitchen cannot cook, because its dish is 86 or unknown. It comes with its reason. | `UnavailableLine`, `LinesUnavailable` |

### 86

Restaurant slang, from American English: an item is **86** when there is none left. The cook says it to the room ("the steak is 86") so nobody sells it again; by extension, to *86* something or someone is to remove it, or to refuse service.

Where it comes from is not settled. What is documented: the term was already in use in a Kansas newspaper in 1920 (found by the researcher Barry Popik), and a 1933 column by Walter Winchell printed it in a glossary of soda-fountain lingo. The most common explanation is that it is slang for *nix*, "none". The popular stories do not hold: Chumley's, the speakeasy at 86 Bedford Street, opened in 1922 and the Empire State Building in 1931, which is too late for a term already printed; the stories of a subway stop, or of a ship crew of 85, are not supported either.

In this model:

- the kitchen declares a dish 86 (`POST /api/kitchen/dishes/{dishId}/eighty-six`);
- the menu of the room follows, so a new order for it is refused;
- an order that was **placed before** the 86 still gets its dish, even if the kitchen reads it later: the position of the order in the log decides, not the stock of the moment;
- a dish already on a ticket is still cooked.

Sources: [Wikipedia](https://en.wikipedia.org/wiki/86_(term)), [Snopes](https://www.snopes.com/fact-check/86/), [Barry Popik](https://barrypopik.com/blog/86_not_from_chumleys_or_empire_state_building), [Mental Floss](https://www.mentalfloss.com/article/51880/where-did-term-86-come).

## The pass

| Term | FR | Meaning | In the code |
| --- | --- | --- | --- |
| **Pass** | passe | The counter where the kitchen puts the dishes that are ready and where the room takes them. | `DishAtThePass` |
| **To serve** | à servir | The list of dishes waiting at the pass. It can lag behind the kitchen until a worker catches it up. | `ToServeProjector`, `GET /to-serve` |
| **Serve** | servir | Take a dish from the pass to the table. Only what was served is billed. | `ServeDish`, `DishServed` |

## The order that does not go through

| Term | FR | Meaning | In the code |
| --- | --- | --- | --- |
| **Partial order** | commande partielle | An order of which some lines are 86. The available lines go to the kitchen at once, the others wait for the guests' choice. | `LinesUnavailable` |
| **Revision** | révision | The waiter asks the guests, who choose replacements. The replacement lines are added to the order, and the unavailable lines are abandoned. A replacement can be 86 too: the order is revised again. | `ReviseOrder`, `TableOrderRevised` |
| **Awaiting kitchen** | en attente de la cuisine | The kitchen has not answered yet. | `FulfilmentStatus::AWAITING_KITCHEN` |
| **Confirmed** | confirmée | The kitchen cooks what the order asks. | `FulfilmentStatus::CONFIRMED` |
| **Awaiting revision** | en attente de révision | Some lines are unavailable; the waiter must go back to the guests. | `FulfilmentStatus::AWAITING_REVISION` |
| **Cancelled** | annulée | The guests gave up the lines that were unavailable. What is already on the ticket is still cooked. | `CancelOrder`, `FulfilmentStatus::CANCELLED` |
| **Closed** | clôturée | The guests paid and left before a revision came. A revision is then **obsolete** and refused. | `FulfilmentStatus::CLOSED`, `ObsoleteRevision` |
| **Abandoned line** | ligne abandonnée | A line given up for good: replaced by a revision, or dropped by a cancellation. | `abandonedLines` |

The guests do not leave without paying: the table is freed by the payment of its bill and by nothing else.

## The till

| Term | FR | Meaning | In the code |
| --- | --- | --- | --- |
| **Cover** | couvert | The guests of one table, as the till knows them. It is the id of the table, under another name: the till does not know the room. | `CoverRef` |
| **Bill** | addition | What a cover owes for the dishes that were served. Open until its balance is gone, then paid and immutable. | `Bill`, `BillStatus` |
| **Served dish** | plat servi | A line of the bill. Its source (ticket and line) makes sure a dish is charged once. | `ServedDish`, `SourceKey` |
| **VAT** | TVA | Prices are tax-inclusive. Food eaten in is 10 %, alcohol 20 %. | `Vat`, `DishKind` |
| **Payment** | paiement | An amount paid on a bill. It may be split between several payments. | `Payment`, `PayBill` |
| **Tip** | pourboire | Added to a payment; it does not reduce the balance. | `Payment::$tipCents` |
| **Balance** | reste à payer | What is still due. At zero the bill is paid. | `Bill::balanceCents()` |
| **Free the table** | libérer la table | The guests are gone: the table is free again. It follows the payment of the bill. | `FreeTable`, `BillPaid` |

## How the contexts talk

These words are technical, but they are the ones of the code and of the discussions about it.

| Term | Meaning | In the code |
| --- | --- | --- |
| **Fact** | Something that happened, named in the past (`DishesAdded`, `BillPaid`). The log only receives facts; a mistake is answered by a new fact. | `Domain/Event` |
| **Contract** | The only public shape of a fact: what a context promises to the others, with a name and a version. A fact that no one outside should read is *internal*. | `Application/Integration`, `#[PublishedAs]`, `#[Internal]` |
| **Consumed message** | What a context reads of a contract of another one, in its own words and types. It keeps what the context needs of the payload and nothing more. | `Infrastructure/Consumed`, `#[Consumes]` |
| **Position** | Where a fact sits in the event log. It is the clock all contexts share, and what decides a late reading. | `StoredEvent::$position` |
| **Saga** | What follows an order between the room and the kitchen, through the facts they publish, with no one in charge. | `OrderFulfilment`, `OrderFulfilmentProcessor` |
| **Idempotency key** | Sent with an order: the same key places it once. | `Idempotency-Key` |
| **If-Match** | Sent with `fire` or `ready`: the version of the ticket the cook saw. If it moved, the command is refused. | `ETag`, `If-Match` |

## Keeping it true

A term is added here when it enters the code, and its entry changes when the code changes. Two words for one thing, or one word for two, is a question for this file before it is a question for the code.
