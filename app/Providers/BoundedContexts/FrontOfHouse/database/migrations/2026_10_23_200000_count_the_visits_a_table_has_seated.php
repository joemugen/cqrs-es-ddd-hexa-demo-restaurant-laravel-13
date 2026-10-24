<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('foh_tables', function (Blueprint $table) {
            $table->unsignedInteger('visits_seated')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('foh_tables', function (Blueprint $table) {
            $table->dropColumn('visits_seated');
        });
    }
};
