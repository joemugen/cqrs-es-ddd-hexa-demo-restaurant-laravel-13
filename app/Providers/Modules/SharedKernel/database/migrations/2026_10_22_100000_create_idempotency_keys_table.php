<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->string('idempotency_key');
            $table->string('command');
            $table->string('fingerprint', 64);
            $table->json('response')->nullable();
            $table->timestamp('created_at');
            $table->primary(['idempotency_key', 'command']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
