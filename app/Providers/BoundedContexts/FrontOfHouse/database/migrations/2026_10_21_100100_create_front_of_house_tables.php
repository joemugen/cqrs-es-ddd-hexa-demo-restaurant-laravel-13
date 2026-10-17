<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foh_tables', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('label');
            $table->unsignedInteger('guest_count')->default(0);
        });

        Schema::create('foh_floor_plan', function (Blueprint $table) {
            $table->string('table_id')->primary();
            $table->string('label');
            $table->unsignedInteger('guest_count');
            $table->string('status');
        });

        Schema::create('foh_menu_items', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('description');
            $table->unsignedInteger('price_cents');
            $table->json('allergens');
            $table->boolean('available')->default(true);
            $table->string('kind');
        });

        Schema::create('foh_table_orders', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('table_id');
            $table->index('table_id');
        });

        Schema::create('foh_table_order_lines', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('table_order_id');
            $table->string('menu_item_id');
            $table->string('name');
            $table->unsignedInteger('quantity');
            $table->string('course');
            $table->unsignedInteger('unit_price_cents');
            $table->string('kind');
            $table->json('allergens');
            $table->index('table_order_id');
        });

        Schema::create('foh_to_serve', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_id');
            $table->string('line_id');
            $table->string('table_id');
            $table->string('table_label');
            $table->string('dish_name');
            $table->unsignedInteger('quantity');
            $table->timestamp('served_at')->nullable();
            $table->unique(['ticket_id', 'line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foh_to_serve');
        Schema::dropIfExists('foh_table_order_lines');
        Schema::dropIfExists('foh_table_orders');
        Schema::dropIfExists('foh_menu_items');
        Schema::dropIfExists('foh_floor_plan');
        Schema::dropIfExists('foh_tables');
    }
};
