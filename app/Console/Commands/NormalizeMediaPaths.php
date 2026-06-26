<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\Message;
use App\Models\Page;
use App\Models\Post;
use App\Models\Story;
use App\Models\User;
use App\Support\MediaStorage;
use Illuminate\Console\Command;

class NormalizeMediaPaths extends Command
{
    protected $signature = 'media:normalize-paths';

    protected $description = 'Convert full MinIO URLs in the database to relative storage paths';

    public function handle(): int
    {
        $updated = 0;

        foreach (User::query()->whereNotNull('avatar')->cursor() as $user) {
            if ($this->normalizeModelPath($user, 'avatar')) {
                $updated++;
            }
        }

        foreach (User::query()->whereNotNull('cover_photo')->cursor() as $user) {
            if ($this->normalizeModelPath($user, 'cover_photo')) {
                $updated++;
            }
        }

        foreach ([Post::class => 'media_path', Story::class => 'media_path', Message::class => 'media_path'] as $model => $column) {
            foreach ($model::query()->whereNotNull($column)->cursor() as $record) {
                if ($this->normalizeModelPath($record, $column)) {
                    $updated++;
                }
            }
        }

        foreach ([Group::class => ['avatar', 'cover_photo'], Page::class => ['avatar', 'cover_photo']] as $model => $columns) {
            foreach ($model::query()->cursor() as $record) {
                foreach ($columns as $column) {
                    if ($record->{$column} && $this->normalizeModelPath($record, $column)) {
                        $updated++;
                    }
                }
            }
        }

        $this->info("Normalized {$updated} media path(s).");

        return self::SUCCESS;
    }

    private function normalizeModelPath(object $model, string $column): bool
    {
        $value = $model->{$column};

        if (! $value || ! MediaStorage::isOurMediaUrl($value)) {
            return false;
        }

        $normalized = MediaStorage::normalizeStoredPath($value);

        if ($normalized === $value) {
            return false;
        }

        $model->forceFill([$column => $normalized])->saveQuietly();

        return true;
    }
}
