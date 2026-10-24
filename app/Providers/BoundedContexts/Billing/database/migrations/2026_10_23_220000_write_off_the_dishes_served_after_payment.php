<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_served_items', function (Blueprint $table) {
            $table->timestamp('written_off_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('billing_served_items', function (Blueprint $table) {
            $table->dropColumn('written_off_at');
        });
    }
};
