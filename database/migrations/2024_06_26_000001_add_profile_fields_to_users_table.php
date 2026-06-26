<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->nullable()->after('name');
            $table->string('avatar')->nullable()->after('password');
            $table->string('cover_photo')->nullable()->after('avatar');
            $table->text('bio')->nullable()->after('cover_photo');
            $table->string('location')->nullable()->after('bio');
            $table->string('website')->nullable()->after('location');
            $table->string('provider')->nullable()->after('website');
            $table->string('provider_id')->nullable()->after('provider');
            $table->timestamp('last_seen_at')->nullable()->after('provider_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username', 'avatar', 'cover_photo', 'bio',
                'location', 'website', 'provider', 'provider_id', 'last_seen_at',
            ]);
        });
    }
};
