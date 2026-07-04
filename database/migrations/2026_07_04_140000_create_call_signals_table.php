<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_signals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 32);
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['to_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_signals');
    }
};
