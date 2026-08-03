<?php

namespace ElSchneider\StatamicSimpleAddress\Http\Controllers;

use ElSchneider\StatamicSimpleAddress\Services\GeocodingService;
use Geocoder\Model\Coordinates;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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

            $results = $this->geocodingService->geocode($geocodeQuery);

            return response()->json([
                'results' => $this->present($results, $request),
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

            $results = $this->geocodingService->reverse($reverseQuery);

            return response()->json([
                'results' => $this->present($results, $request),
            ]);
        } catch (\Exception $e) {
            return $this->handleGeocodingError($e);
        }
    }

    /**
     * Apply the per-request shaping the cached payload deliberately leaves out.
     *
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, array<string, mixed>>
     */
    private function present(array $results, Request $request): array
    {
        $exclude = $request->input('exclude_fields', []);

        return array_map(function (array $result) use ($exclude) {
            $result = Arr::except($result, $exclude);

            // adminLevels is keyed by level; cast so JSON keeps it an object, not a list.
            if (isset($result['adminLevels'])) {
                $result['adminLevels'] = (object) $result['adminLevels'];
            }

            return $result;
        }, $results);
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
