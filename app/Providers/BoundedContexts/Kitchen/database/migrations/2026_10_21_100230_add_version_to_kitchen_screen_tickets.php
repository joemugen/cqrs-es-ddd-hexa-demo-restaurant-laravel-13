<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kitchen_screen_tickets', function (Blueprint $table) {
            $table->unsignedInteger('version')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('kitchen_screen_tickets', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }
};
