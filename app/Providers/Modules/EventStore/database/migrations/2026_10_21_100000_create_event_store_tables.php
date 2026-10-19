<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id('position');
            $table->string('stream_id');
            $table->unsignedInteger('version');
            $table->string('type');
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->unique(['stream_id', 'version']);
        });

        Schema::create('checkpoints', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->unsignedBigInteger('position');
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkpoints');
        Schema::dropIfExists('events');
    }
};
