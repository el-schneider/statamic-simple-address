<?php

namespace ElSchneider\StatamicSimpleAddress\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

use function Illuminate\Support\defer;

class AddressSearchController
{
    /**
     * Enforce minimum debounce delay for a provider to prevent exceeding rate limits.
     * Latest request wins: if a newer request arrives during throttle window, earlier ones are skipped.
     *
     * @return bool True if this request should proceed, false if superseded by a newer request
     */
    private function enforceMinimumDelay(string $provider, int $minDelayMs): bool
    {
        if ($minDelayMs <= 0) {
            return true;
        }

        $cacheKey = "simple_address:throttle:{$provider}";
        $timeKey = "{$cacheKey}:time";
        $idKey = "{$cacheKey}:id";

        $lastRequestTime = Cache::get($timeKey);
        $currentRequestId = uniqid();

        // Register this request
        Cache::put($idKey, $currentRequestId, 60);

        if ($lastRequestTime) {
            $elapsed = now()->diffInMilliseconds($lastRequestTime);
            if ($elapsed < $minDelayMs) {
                $sleepMs = $minDelayMs - $elapsed;
                usleep($sleepMs * 1000);

                // After waiting, check if a newer request came in
                $latestRequestId = Cache::get($idKey);
                if ($latestRequestId !== $currentRequestId) {
                    // A newer request arrived while we were waiting
                    return false;
                }
            }
        }

        // Record this request's completion time
        Cache::put($timeKey, now(), 60);

        return true;
    }

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'query' => 'required|string|min:1|max:255',
                'provider' => 'required|string|in:'.implode(',', array_keys(config('simple-address.providers', []))),
                'additional_exclude_fields' => 'array',
                'additional_exclude_fields.*' => 'string',
                'countries' => 'array',
                'countries.*' => 'string',
                'language' => 'string|nullable',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            $provider = $validated['provider'];
            $providerConfig = config("simple-address.providers.{$provider}");

            if (! $providerConfig) {
                return response()->json([
                    'message' => "Provider '{$provider}' not found in configuration",
                    'errors' => [
                        'provider' => ["Provider '{$provider}' not found in configuration"],
                    ],
                ], 400);
            }

            $transformerClass = $providerConfig['transformer'] ?? null;
            if (! $transformerClass || ! class_exists($transformerClass)) {
                return response()->json([
                    'message' => "Transformer for provider '{$provider}' not found",
                ], 500);
            }

            // Merge default and additional exclusions
            $defaultExclusions = $providerConfig['default_exclude_fields'] ?? [];
            $allExclusions = array_unique(array_merge(
                $defaultExclusions,
                $validated['additional_exclude_fields'] ?? []
            ));

            // Build cache key from query params (excluding additional_exclude_fields)
            $cacheKeyData = [
                'query' => $validated['query'],
                'provider' => $provider,
                'countries' => $validated['countries'] ?? [],
                'language' => $validated['language'] ?? null,
            ];
            $cacheKey = 'address-search:'.hash('sha256', json_encode($cacheKeyData));

            // Check cache first - cache hits bypass throttling
            if (Cache::has($cacheKey)) {
                $cachedResponse = Cache::get($cacheKey);
                $transformer = new $transformerClass($allExclusions);
                $result = $transformer->transform($cachedResponse);

                return response()->json($result->toArray(), 200);
            }

            // Enforce minimum debounce delay for this provider (only for API calls, not cache hits)
            // If a newer request came in, skip this one (latest request wins)
            $minDelay = $providerConfig['min_debounce_delay'] ?? 0;
            if (! $this->enforceMinimumDelay($provider, $minDelay)) {
                return response()->json([
                    'results' => [],
                    'message' => 'Request superseded by newer request',
                ], 200);
            }

            // Build request to external provider
            $url = $providerConfig['base_url'];
            $params = [
                $providerConfig['freeform_search_key'] => $validated['query'],
                ...($providerConfig['request_options'] ?? []),
            ];

            if (! empty($validated['countries'])) {
                $params['countrycodes'] = implode(',', $validated['countries']);
            }

            if ($validated['language'] ?? null) {
                $params['accept-language'] = $validated['language'];
            }

            // Add API key if required
            if (! empty($providerConfig['api_key'])) {
                $apiKeyParam = $providerConfig['api_key_param_name'] ?? 'api_key';
                $params[$apiKeyParam] = $providerConfig['api_key'];
            }

            // Make request
            $response = Http::withHeaders([
                'User-Agent' => config('app.name', 'Statamic'),
            ])->get($url, $params);

            if (! $response->successful()) {
                return response()->json([
                    'message' => 'Provider API request failed',
                    'status' => $response->status(),
                ], 502);
            }

            $apiResponse = $response->json();

            // Transform response
            $transformer = new $transformerClass($allExclusions);
            $result = $transformer->transform($apiResponse);

            // Cache the API response for 1 year after response is sent (deferred to avoid blocking)
            defer(fn () => Cache::put($cacheKey, $apiResponse, now()->addYear()));

            return response()->json($result->toArray(), 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Internal server error',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
