<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_served_items', function (Blueprint $table) {
            $table->string('source_key')->primary();
            $table->string('cover_ref');
            $table->string('description');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price_cents');
            $table->string('kind');
            $table->string('bill_id')->nullable();
            $table->index('cover_ref');
        });

        Schema::create('billing_bills', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('cover_ref');
            $table->string('status');
            $table->unsignedInteger('food_gross_cents');
            $table->unsignedInteger('alcohol_gross_cents');
            $table->unsignedInteger('vat_cents');
            $table->unsignedInteger('total_cents');
            $table->unsignedInteger('balance_cents');
            $table->unsignedInteger('tip_cents');
            $table->index('cover_ref');
        });

        Schema::create('billing_bill_lines', function (Blueprint $table) {
            $table->id();
            $table->string('bill_id');
            $table->string('source_key');
            $table->string('description');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price_cents');
            $table->string('kind');
            $table->unsignedInteger('vat_cents');
            $table->index('bill_id');
        });

        Schema::create('billing_payments', function (Blueprint $table) {
            $table->id();
            $table->string('bill_id');
            $table->unsignedInteger('amount_cents');
            $table->unsignedInteger('tip_cents');
            $table->index('bill_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_payments');
        Schema::dropIfExists('billing_bill_lines');
        Schema::dropIfExists('billing_bills');
        Schema::dropIfExists('billing_served_items');
    }
};
