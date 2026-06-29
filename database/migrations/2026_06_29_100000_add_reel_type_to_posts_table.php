<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('posts')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE posts MODIFY COLUMN type ENUM('text', 'image', 'video', 'reel') NOT NULL DEFAULT 'text'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('posts')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::table('posts')->where('type', 'reel')->update(['type' => 'video']);
            DB::statement("ALTER TABLE posts MODIFY COLUMN type ENUM('text', 'image', 'video') NOT NULL DEFAULT 'text'");
        }
    }
};
