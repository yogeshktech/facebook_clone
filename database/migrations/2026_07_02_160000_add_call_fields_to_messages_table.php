<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('message_type', 20)->default('text')->after('body');
            $table->string('call_status', 20)->nullable()->after('message_type');
            $table->boolean('call_is_video')->nullable()->after('call_status');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['message_type', 'call_status', 'call_is_video']);
        });
    }
};
