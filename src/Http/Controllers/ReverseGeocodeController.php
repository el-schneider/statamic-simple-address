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

class ReverseGeocodeController
{
    public function __construct(
        private ProviderApiService $providerService,
        private ThrottleService $throttleService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'lat' => 'required|numeric|between:-90,90',
                'lon' => 'required|numeric|between:-180,180',
                'provider' => 'required|string|in:'.implode(',', $this->providerService->getAvailableProviders()),
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

            $cacheKeyData = [
                'reverse' => true,
                'lat' => $validated['lat'],
                'lon' => $validated['lon'],
                'provider' => $providerName,
                'language' => $validated['language'] ?? null,
            ];
            $cacheKey = $this->providerService->generateCacheKey('address-reverse', $cacheKeyData);

            $cached = $this->providerService->getCachedOrFetch($cacheKey, function () use ($provider, $validated, $providerName) {
                // Enforce minimum delay
                if (! $this->throttleService->enforceMinimumDelay($providerName, $provider->getMinDebounceDelay())) {
                    return null;
                }

                $request = $provider->buildReverseRequest(
                    (float) $validated['lat'],
                    (float) $validated['lon'],
                    ['language' => $validated['language'] ?? null]
                );

                $response = Http::withHeaders([
                    'User-Agent' => config('app.name', 'Statamic'),
                ])->get($request['url'], $request['params']);

                if (! $response->successful()) {
                    Log::warning('simple-address: reverse geocoding API request failed', [
                        'provider' => $providerName,
                        'lat' => $validated['lat'],
                        'lon' => $validated['lon'],
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

            $result = $provider->transformReverseResponse($cached['data']);
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
            Log::error('simple-address: unexpected error during reverse geocoding', [
                'provider' => $validated['provider'] ?? null,
                'lat' => $validated['lat'] ?? null,
                'lon' => $validated['lon'] ?? null,
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
