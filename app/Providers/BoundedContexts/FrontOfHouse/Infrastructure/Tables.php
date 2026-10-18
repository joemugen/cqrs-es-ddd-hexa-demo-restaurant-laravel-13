<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure;

/**
 * Table names. Migrations keep the literal name on purpose: a migration describes the schema as it was, and must not move when a constant is renamed.
 */
final class Tables
{
    public const FLOOR_PLAN = 'foh_floor_plan';

    public const MENU_ITEMS = 'foh_menu_items';

    public const TABLES = 'foh_tables';

    public const TABLE_ORDERS = 'foh_table_orders';

    public const TABLE_ORDER_LINES = 'foh_table_order_lines';

    public const TO_SERVE = 'foh_to_serve';
}
