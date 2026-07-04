<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CallInboxService
{
    private const TTL_SECONDS = 90;

    public function push(int $toUserId, array $payload): string
    {
        $id = (string) Str::uuid();
        $entry = array_merge($payload, [
            '_id' => $id,
            '_at' => microtime(true),
        ]);

        $key = $this->key($toUserId);
        $inbox = Cache::get($key, []);
        $inbox[] = $entry;
        $inbox = array_slice($inbox, -80);
        Cache::put($key, $inbox, now()->addSeconds(self::TTL_SECONDS));

        return $id;
    }

    public function pull(int $userId, ?float $after = null): array
    {
        $inbox = Cache::get($this->key($userId), []);

        if ($after !== null) {
            $inbox = array_values(array_filter(
                $inbox,
                static fn (array $item) => (float) ($item['_at'] ?? 0) > $after
            ));
        }

        return $inbox;
    }

    public function clearForUsers(int ...$userIds): void
    {
        foreach (array_unique($userIds) as $userId) {
            Cache::forget($this->key((int) $userId));
        }
    }

    private function key(int $userId): string
    {
        return "call_inbox:{$userId}";
    }
}
