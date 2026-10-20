<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metrics', function (Blueprint $table) {
            $table->string('metric', 64);
            $table->string('labels', 512);
            $table->unsignedBigInteger('occurrences')->default(0);
            $table->double('total_ms')->default(0);
            $table->double('max_ms')->default(0);
            $table->primary(['metric', 'labels']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metrics');
    }
};
