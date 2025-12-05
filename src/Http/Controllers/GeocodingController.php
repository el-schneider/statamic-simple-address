<?php

namespace ElSchneider\StatamicSimpleAddress\Http\Controllers;

use ElSchneider\StatamicSimpleAddress\Http\Resources\AddressResource;
use ElSchneider\StatamicSimpleAddress\Services\GeocodingService;
use Geocoder\Model\Coordinates;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $language = $request->input('language');

        try {
            $geocodeQuery = GeocodeQuery::create($query);

            if (! empty($countries)) {
                $geocodeQuery = $geocodeQuery->withData('countrycodes', $countries);
            }

            if ($language) {
                $geocodeQuery = $geocodeQuery->withLocale($language);
            }

            $addressResults = $this->geocodingService->geocode($geocodeQuery);

            return response()->json([
                'results' => AddressResource::collection($addressResults)->resolve($request),
            ]);
        } catch (\Exception $e) {
            return $this->handleGeocodingError($e);
        }
    }

    public function reverse(Request $request): JsonResponse
    {
        $lat = $request->input('lat');
        $lon = $request->input('lon');
        $language = $request->input('language');

        try {
            $coordinates = new Coordinates((float) $lat, (float) $lon);
            $reverseQuery = ReverseQuery::create($coordinates);

            if ($language) {
                $reverseQuery = $reverseQuery->withLocale($language);
            }

            $addressResults = $this->geocodingService->reverse($reverseQuery);

            return response()->json([
                'results' => AddressResource::collection($addressResults)->resolve($request),
            ]);
        } catch (\Exception $e) {
            return $this->handleGeocodingError($e);
        }
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
