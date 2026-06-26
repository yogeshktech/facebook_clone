<?php

namespace App\Console\Commands;

use App\Models\Story;
use Illuminate\Console\Command;

class PruneExpiredStories extends Command
{
    protected $signature = 'stories:prune';

    protected $description = 'Delete expired stories and their media files';

    public function handle(): int
    {
        $count = Story::pruneExpired();

        $this->info("Pruned {$count} expired ".str('story')->plural($count).'.');

        return self::SUCCESS;
    }
}
