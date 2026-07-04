<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('reply_to_id')->nullable()->after('user_id')->constrained('messages')->nullOnDelete();
            $table->timestamp('edited_at')->nullable()->after('delivered_at');
            $table->timestamp('deleted_for_everyone_at')->nullable()->after('edited_at');
            $table->foreignId('deleted_by')->nullable()->after('deleted_for_everyone_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('conversation_user', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable()->after('last_read_at');
            $table->string('role', 20)->default('member')->after('hidden_at');
        });

        Schema::create('message_user_deletes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['message_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_user_deletes');

        Schema::table('conversation_user', function (Blueprint $table) {
            $table->dropColumn(['hidden_at', 'role']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reply_to_id');
            $table->dropColumn(['edited_at', 'deleted_for_everyone_at']);
            $table->dropConstrainedForeignId('deleted_by');
        });
    }
};
