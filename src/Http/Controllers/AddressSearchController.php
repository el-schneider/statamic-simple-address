<?php

namespace ElSchneider\StatamicSimpleAddress\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class AddressSearchController
{
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

            // Transform response
            $transformer = new $transformerClass($allExclusions);
            $result = $transformer->transform($response->json());

            return response()->json($result->toArray(), 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Internal server error',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
