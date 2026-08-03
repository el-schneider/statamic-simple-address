<?php

namespace ElSchneider\StatamicSimpleAddress\Http\Controllers;

use Closure;
use ElSchneider\StatamicSimpleAddress\Services\GeocodingService;
use ElSchneider\StatamicSimpleAddress\Support\LocationPayload;
use Geocoder\Model\Coordinates;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GeocodingController
{
    public function __construct(
        private GeocodingService $geocodingService
    ) {}

    public function search(Request $request): JsonResponse
    {
        $query = $request->input('query');
        $countries = $request->input('countries', []);
        $language = $this->normalizeLanguage($request->input('language'));

        try {
            $geocodeQuery = GeocodeQuery::create($query);

            if (! empty($countries)) {
                $geocodeQuery = $geocodeQuery->withData('countrycodes', $countries);
            }

            if ($language) {
                $geocodeQuery = $geocodeQuery->withLocale($language);
            }

            return response()->json([
                'results' => $this->cached($request, fn () => $this->present(
                    $this->geocodingService->geocode($geocodeQuery), $request
                )),
            ]);
        } catch (\Exception $e) {
            return $this->handleGeocodingError($e);
        }
    }

    public function reverse(Request $request): JsonResponse
    {
        $lat = $request->input('lat');
        $lon = $request->input('lon');
        $language = $this->normalizeLanguage($request->input('language'));

        try {
            $coordinates = new Coordinates((float) $lat, (float) $lon);
            $reverseQuery = ReverseQuery::create($coordinates);

            if ($language) {
                $reverseQuery = $reverseQuery->withLocale($language);
            }

            return response()->json([
                'results' => $this->cached($request, fn () => $this->present(
                    $this->geocodingService->reverse($reverseQuery), $request
                )),
            ]);
        } catch (\Exception $e) {
            return $this->handleGeocodingError($e);
        }
    }

    private function present(array $locations, Request $request): array
    {
        $exclude = $request->input('exclude_fields', []);

        return array_map(
            fn ($location) => Arr::except(LocationPayload::fromLocation($location), $exclude),
            $locations
        );
    }

    /**
     * Cached values have to be plain data: under `serializable_classes = false` Laravel
     * returns nothing else, and a geocoder result cannot be rebuilt from it.
     *
     * The key reads parsed input rather than the raw body, which a form-encoded post
     * leaves empty.
     */
    private function cached(Request $request, Closure $lookup): array
    {
        if (! config('simple-address.cache.enabled')) {
            return $lookup();
        }

        $input = $request->all();
        ksort($input);

        $key = sprintf(
            'simple-address.%s.%s.%s',
            config('simple-address.provider'),
            $request->path(),
            sha1(json_encode($input))
        );

        return Cache::store(config('simple-address.cache.store'))
            ->remember($key, config('simple-address.cache.duration'), $lookup);
    }

    /**
     * Normalize the incoming language value to an RFC2616-style string.
     *
     * The `language` fieldtype config is a taggable field, so the frontend
     * can POST an array (e.g. ['en', 'de']). Geocoder's withLocale() is
     * strictly typed as string, so we collapse arrays to a comma-separated list.
     */
    private function normalizeLanguage(mixed $language): ?string
    {
        if (is_array($language)) {
            return implode(',', $language) ?: null;
        }

        return $language;
    }

    private function handleGeocodingError(\Exception $e): JsonResponse
    {
        Log::error('Geocoding failed', [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'message' => 'Geocoding failed. Check the logs for more information.',
        ], 500);
    }
}
