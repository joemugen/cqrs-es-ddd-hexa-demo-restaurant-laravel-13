<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ledger of a cover is an aggregate: what it knows of the current bill, and a version that a save checks. The dishes it holds
 * are the rows of billing_served_items, placed on a bill or written off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_cover_ledgers', function (Blueprint $table) {
            $table->string('cover_ref')->primary();
            $table->string('current_bill_id')->nullable();
            $table->boolean('current_bill_settled')->default(false);
            $table->unsignedInteger('version')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_cover_ledgers');
    }
};
