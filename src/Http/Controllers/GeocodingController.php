<?php

namespace ElSchneider\StatamicSimpleAddress\Http\Controllers;

use Closure;
use ElSchneider\StatamicSimpleAddress\Exceptions\ProviderApiException;
use ElSchneider\StatamicSimpleAddress\Providers\AbstractProvider;
use ElSchneider\StatamicSimpleAddress\Services\GeocodingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class GeocodingController
{
    public function __construct(
        private GeocodingService $geocodingService,
    ) {}

    public function search(Request $request): JsonResponse
    {
        return $this->handleRequest($request, [
            'rules' => [
                'query' => 'required|string|min:1|max:255',
                'provider' => 'required|string|in:'.implode(',', $this->geocodingService->getAvailableProviders()),
                'countries' => 'array',
                'countries.*' => 'string',
                'language' => 'string|nullable',
            ],
            'cachePrefix' => 'address-search',
            'cacheKeyBuilder' => fn (array $v) => [
                'query' => $v['query'],
                'provider' => $v['provider'],
                'countries' => $v['countries'] ?? [],
                'language' => $v['language'] ?? null,
            ],
            'requestBuilder' => fn (AbstractProvider $p, array $v) => $p->buildSearchRequest($v['query'], [
                'countries' => $v['countries'] ?? [],
                'language' => $v['language'] ?? null,
            ]),
            'responseTransformer' => fn (AbstractProvider $p, array $data) => $p->transformResponse($data),
            'logContext' => fn (array $v) => ['query' => $v['query'] ?? null, 'provider' => $v['provider'] ?? null],
            'logMessage' => 'address search',
        ]);
    }

    public function reverse(Request $request): JsonResponse
    {
        return $this->handleRequest($request, [
            'rules' => [
                'lat' => 'required|numeric|between:-90,90',
                'lon' => 'required|numeric|between:-180,180',
                'provider' => 'required|string|in:'.implode(',', $this->geocodingService->getAvailableProviders()),
                'language' => 'string|nullable',
            ],
            'cachePrefix' => 'address-reverse',
            'cacheKeyBuilder' => fn (array $v) => [
                'reverse' => true,
                'lat' => $v['lat'],
                'lon' => $v['lon'],
                'provider' => $v['provider'],
                'language' => $v['language'] ?? null,
            ],
            'requestBuilder' => fn (AbstractProvider $p, array $v) => $p->buildReverseRequest(
                (float) $v['lat'],
                (float) $v['lon'],
                ['language' => $v['language'] ?? null]
            ),
            'responseTransformer' => fn (AbstractProvider $p, array $data) => $p->transformReverseResponse($data),
            'logContext' => fn (array $v) => ['lat' => $v['lat'] ?? null, 'lon' => $v['lon'] ?? null, 'provider' => $v['provider'] ?? null],
            'logMessage' => 'reverse geocoding',
        ]);
    }

    /**
     * @param  array{
     *     rules: array<string, string>,
     *     cachePrefix: string,
     *     cacheKeyBuilder: Closure,
     *     requestBuilder: Closure,
     *     responseTransformer: Closure,
     *     logContext: Closure,
     *     logMessage: string
     * }  $config
     */
    private function handleRequest(Request $request, array $config): JsonResponse
    {
        try {
            $validated = $request->validate($config['rules']);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            $providerName = $validated['provider'];
            $provider = $this->geocodingService->resolveProvider($providerName);
            $this->geocodingService->validateApiKey($provider, $providerName);

            $cacheKey = $this->geocodingService->generateCacheKey(
                $config['cachePrefix'],
                $config['cacheKeyBuilder']($validated)
            );

            $cached = $this->geocodingService->getCachedOrFetch($cacheKey, function () use ($provider, $validated, $providerName, $config) {
                if (! $this->geocodingService->enforceMinimumDelay($providerName, $provider->getMinDebounceDelay())) {
                    return null;
                }

                $apiRequest = $config['requestBuilder']($provider, $validated);

                try {
                    return $this->geocodingService->fetch($apiRequest['url'], $apiRequest['params']);
                } catch (ProviderApiException $e) {
                    Log::warning("simple-address: {$config['logMessage']} API request failed", [
                        'provider' => $providerName,
                        ...$config['logContext']($validated),
                        'status' => $e->getStatusCode(),
                        'url' => $apiRequest['url'],
                    ]);

                    throw $e;
                }
            });

            if ($cached['data'] === null) {
                return response()->json(['results' => []], 200);
            }

            $result = $config['responseTransformer']($provider, $cached['data']);
            $headers = ['X-Cache' => $cached['cached'] ? 'HIT' : 'MISS'];

            return response()->json($result->toArray(), 200, $headers);

        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (ProviderApiException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => $e->getStatusCode(),
            ], 502);
        } catch (\Exception $e) {
            Log::error("simple-address: unexpected error during {$config['logMessage']}", [
                ...$config['logContext']($validated),
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
