<?php

namespace ElSchneider\StatamicSimpleAddress\Http\Controllers;

use ElSchneider\StatamicSimpleAddress\Exceptions\ProviderApiException;
use ElSchneider\StatamicSimpleAddress\Services\ProviderApiService;
use ElSchneider\StatamicSimpleAddress\Services\ThrottleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AddressSearchController
{
    public function __construct(
        private ProviderApiService $providerService,
        private ThrottleService $throttleService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'query' => 'required|string|min:1|max:255',
                'provider' => 'required|string|in:'.implode(',', $this->providerService->getAvailableProviders()),
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
            $providerName = $validated['provider'];
            $provider = $this->providerService->resolveProvider($providerName);
            $this->providerService->validateApiKey($provider, $providerName);

            if (! empty($validated['additional_exclude_fields'])) {
                $provider->setExcludeFields($validated['additional_exclude_fields']);
            }

            $cacheKeyData = [
                'query' => $validated['query'],
                'provider' => $providerName,
                'countries' => $validated['countries'] ?? [],
                'language' => $validated['language'] ?? null,
            ];
            $cacheKey = $this->providerService->generateCacheKey('address-search', $cacheKeyData);

            $cached = $this->providerService->getCachedOrFetch($cacheKey, function () use ($provider, $validated, $providerName) {
                // Enforce minimum delay
                if (! $this->throttleService->enforceMinimumDelay($providerName, $provider->getMinDebounceDelay())) {
                    return null;
                }

                $request = $provider->buildSearchRequest($validated['query'], [
                    'countries' => $validated['countries'] ?? [],
                    'language' => $validated['language'] ?? null,
                ]);

                $response = Http::withHeaders([
                    'User-Agent' => config('app.name', 'Statamic'),
                ])->get($request['url'], $request['params']);

                if (! $response->successful()) {
                    Log::warning('simple-address: provider API request failed', [
                        'provider' => $providerName,
                        'query' => $validated['query'],
                        'status' => $response->status(),
                        'url' => $request['url'],
                        'response_body' => $response->body(),
                    ]);

                    throw new ProviderApiException('Provider API request failed', $response->status());
                }

                return $response->json();
            });

            if ($cached['data'] === null) {
                return response()->json(['results' => []], 200);
            }

            $result = $provider->transformResponse($cached['data']);
            $headers = $cached['cached'] ? ['X-Cache' => 'HIT'] : ['X-Cache' => 'MISS'];

            return response()->json($result->toArray(), 200, $headers);

        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
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
