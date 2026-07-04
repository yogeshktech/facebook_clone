<?php

namespace App\Services;

use App\Models\CallSignal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CallInboxService
{
    private const TTL_SECONDS = 120;

    public function push(int $toUserId, array $payload): string
    {
        $id = (string) Str::uuid();
        $fromUserId = (int) ($payload['from_user']['id'] ?? 0);
        $type = (string) ($payload['type'] ?? 'unknown');
        $at = microtime(true);

        $entry = array_merge($payload, [
            '_id' => $id,
            '_at' => $at,
        ]);

        // Primary: database (works across servers, survives cache misconfig)
        try {
            if ($fromUserId > 0) {
                CallSignal::query()->create([
                    'id' => $id,
                    'to_user_id' => $toUserId,
                    'from_user_id' => $fromUserId,
                    'type' => $type,
                    'payload' => $entry,
                    'created_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            logger()->warning('CallSignal DB write failed, using cache only: '.$e->getMessage());
        }

        // Secondary: cache for fast reads
        try {
            $key = $this->cacheKey($toUserId);
            $inbox = Cache::get($key, []);
            $inbox[] = $entry;
            $inbox = array_slice($inbox, -80);
            Cache::put($key, $inbox, now()->addSeconds(self::TTL_SECONDS));
        } catch (\Throwable $e) {
            logger()->warning('Call inbox cache write failed: '.$e->getMessage());
        }

        // Cleanup old rows occasionally
        if (random_int(1, 20) === 1) {
            try {
                CallSignal::query()
                    ->where('created_at', '<', now()->subMinutes(5))
                    ->delete();
            } catch (\Throwable $e) {
            }
        }

        return $id;
    }

    public function pull(int $userId, ?float $after = null): array
    {
        $fromDb = [];
        $minTime = ($after !== null && $after > 0) ? $after : (microtime(true) - self::TTL_SECONDS);

        try {
            // Fetch last 2 hours to be immune to timezone offsets (e.g. UTC vs local time)
            $rows = CallSignal::query()
                ->where('to_user_id', $userId)
                ->where('created_at', '>=', now()->subHours(2))
                ->orderBy('created_at')
                ->get();

            $fromDb = $rows
                ->map(fn (CallSignal $row) => $row->payload)
                ->filter(fn ($payload) => is_array($payload) && (float) ($payload['_at'] ?? 0) > $minTime)
                ->values()
                ->all();
        } catch (\Throwable $e) {
            logger()->warning('CallSignal DB read failed: '.$e->getMessage());
        }

        if ($fromDb) {
            return $fromDb;
        }

        // Fallback to cache
        try {
            $inbox = Cache::get($this->cacheKey($userId), []);
            $inbox = array_values(array_filter(
                $inbox,
                static fn (array $item) => (float) ($item['_at'] ?? 0) > $minTime
            ));

            return $inbox;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function clearForUsers(int ...$userIds): void
    {
        foreach (array_unique($userIds) as $userId) {
            try {
                Cache::forget($this->cacheKey((int) $userId));
            } catch (\Throwable $e) {
            }

            try {
                CallSignal::query()->where('to_user_id', (int) $userId)->delete();
            } catch (\Throwable $e) {
            }
        }
    }

    private function cacheKey(int $userId): string
    {
        return "call_inbox:{$userId}";
    }
}
