<?php

namespace ElSchneider\StatamicSimpleAddress\Http\Controllers;

use ElSchneider\StatamicSimpleAddress\Exceptions\ProviderApiException;
use ElSchneider\StatamicSimpleAddress\Services\ProviderApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AddressSearchController
{
    private ProviderApiService $providerService;

    public function __construct(ProviderApiService $providerService)
    {
        $this->providerService = $providerService;
    }

    /**
     * Enforce minimum debounce delay for a provider to prevent exceeding rate limits.
     * Uses atomic locking to check if enough time has passed since the last request.
     * If not enough time has passed, returns false (request should be skipped).
     * The frontend's debounce will naturally retry after a delay.
     *
     * @return bool True if this request should proceed, false if too soon (will retry naturally)
     */
    private function enforceMinimumDelay(string $provider, int $minDelayMs): bool
    {
        if ($minDelayMs <= 0) {
            return true;
        }

        $lockKey = "simple_address:throttle:{$provider}";
        $timeKey = "{$lockKey}:time";

        // Try to acquire lock without blocking
        $lock = Cache::lock($lockKey, 5);
        if (! $lock->get()) {
            // Another request is currently being processed
            return false;
        }

        try {
            $lastRequestTime = Cache::get($timeKey);

            if ($lastRequestTime) {
                $elapsed = now()->diffInMilliseconds($lastRequestTime);
                if ($elapsed < $minDelayMs) {
                    // Not enough time has passed yet
                    return false;
                }
            }

            // Update the time for this request (held under lock)
            Cache::put($timeKey, now(), 60);

            return true;
        } finally {
            $lock->release();
        }
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
            $providerConfig = $this->providerService->loadProviderConfig($provider);
            $this->providerService->validateApiKey($providerConfig, $provider);
            $this->providerService->validateTransformer($providerConfig);

            $allExclusions = $this->providerService->buildExclusionList(
                $providerConfig,
                $validated['additional_exclude_fields'] ?? []
            );

            $cacheKeyData = [
                'query' => $validated['query'],
                'provider' => $provider,
                'countries' => $validated['countries'] ?? [],
                'language' => $validated['language'] ?? null,
            ];
            $cacheKey = $this->providerService->generateCacheKey('address-search', $cacheKeyData);

            $cached = $this->providerService->getCachedOrFetch($cacheKey, function () use (
                $providerConfig,
                $validated,
                $provider
            ) {
                // Enforce minimum delay
                $minDelay = $providerConfig['min_debounce_delay'] ?? 0;
                if (! $this->enforceMinimumDelay($provider, $minDelay)) {
                    return null;
                }

                // Build and execute request
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

                if (! empty($providerConfig['api_key'])) {
                    $apiKeyParam = $providerConfig['api_key_param_name'] ?? 'api_key';
                    $params[$apiKeyParam] = $providerConfig['api_key'];
                }

                $response = Http::withHeaders([
                    'User-Agent' => config('app.name', 'Statamic'),
                ])->get($url, $params);

                if (! $response->successful()) {
                    Log::warning('simple-address: provider API request failed', [
                        'provider' => $provider,
                        'query' => $validated['query'],
                        'status' => $response->status(),
                        'url' => $url,
                        'response_body' => $response->body(),
                    ]);

                    throw new ProviderApiException('Provider API request failed', $response->status());
                }

                return $response->json();
            });

            if ($cached['data'] === null) {
                return response()->json(['results' => []], 200);
            }

            $transformerClass = $providerConfig['transformer'];
            $transformer = new $transformerClass($allExclusions);
            $result = $transformer->transform($cached['data']);

            $headers = $cached['cached'] ? ['X-Cache' => 'HIT'] : ['X-Cache' => 'MISS'];

            return response()->json($result->toArray(), 200, $headers);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        } catch (ProviderApiException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => $e->getStatusCode(),
            ], 502);
        } catch (\Exception $e) {
            Log::error('simple-address: unexpected error during address search', [
                'query' => $validated['query'] ?? null,
                'provider' => $validated['provider'] ?? null,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Internal server error',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
