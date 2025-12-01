<?php

namespace ElSchneider\StatamicSimpleAddress\Http\Controllers;

use ElSchneider\StatamicSimpleAddress\Http\Resources\AddressResource;
use ElSchneider\StatamicSimpleAddress\Services\GeocodingService;
use Geocoder\Model\Coordinates;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            return response()->json([
                'message' => 'Geocoding failed',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
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
            return response()->json([
                'message' => 'Reverse geocoding failed',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
