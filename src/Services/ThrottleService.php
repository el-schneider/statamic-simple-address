<?php

namespace ElSchneider\StatamicSimpleAddress\Services;

use Illuminate\Support\Facades\Cache;

class ThrottleService
{
    /**
     * Enforce minimum debounce delay for a provider to prevent exceeding rate limits.
     * Uses atomic locking to check if enough time has passed since the last request.
     *
     * @return bool True if this request should proceed, false if too soon
     */
    public function enforceMinimumDelay(string $provider, int $minDelayMs): bool
    {
        if ($minDelayMs <= 0) {
            return true;
        }

        $lockKey = "simple_address:throttle:{$provider}";
        $timeKey = "{$lockKey}:time";

        $lock = Cache::lock($lockKey, 5);
        if (! $lock->get()) {
            return false;
        }

        try {
            $lastRequestTime = Cache::get($timeKey);

            if ($lastRequestTime) {
                $elapsed = now()->diffInMilliseconds($lastRequestTime);
                if ($elapsed < $minDelayMs) {
                    return false;
                }
            }

            Cache::put($timeKey, now(), 60);

            return true;
        } finally {
            $lock->release();
        }
    }
}
