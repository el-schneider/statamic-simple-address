<?php

namespace ElSchneider\StatamicSimpleAddress\Tests\Feature\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Statamic\Facades\User;
use Tests\TestCase;

class ReverseGeocodeControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        // Create and authenticate a user for CP access
        $user = User::make()->email('test@example.com')->id('test-user')->makeSuper();
        $user->save();
        $this->actingAs($user);
    }

    public function test_reverse_geocoding_returns_normalized_response()
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'place_id' => 260029001,
                'lat' => '51.5153449',
                'lon' => '-0.0918298',
                'display_name' => 'Corporation of London, England',
                'address' => ['road' => 'Main Street', 'city' => 'London'],
            ]),
        ]);

        $response = $this->postJson('/cp/simple-address/reverse', [
            'lat' => '51.5153449',
            'lon' => '-0.0918298',
            'provider' => 'nominatim',
            'language' => 'en',
            'additional_exclude_fields' => [],
        ]);

        $response->assertSuccessful()
            ->assertJsonStructure(['results' => [['label', 'lat', 'lon', 'address']]]);
    }

    public function test_reverse_geocoding_with_invalid_lat_returns_error()
    {
        $response = $this->postJson('/cp/simple-address/reverse', [
            'lat' => '91',  // Invalid: > 90
            'lon' => '-0.09',
            'provider' => 'nominatim',
            'language' => 'en',
            'additional_exclude_fields' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('lat');
    }

    public function test_reverse_geocoding_with_invalid_lon_returns_error()
    {
        $response = $this->postJson('/cp/simple-address/reverse', [
            'lat' => '51.51',
            'lon' => '181',  // Invalid: > 180
            'provider' => 'nominatim',
            'language' => 'en',
            'additional_exclude_fields' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('lon');
    }

    public function test_reverse_geocoding_with_invalid_provider_returns_error()
    {
        $response = $this->postJson('/cp/simple-address/reverse', [
            'lat' => '51.51',
            'lon' => '-0.09',
            'provider' => 'invalid_provider',
            'language' => 'en',
            'additional_exclude_fields' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('provider');
    }

    public function test_reverse_geocoding_applies_default_exclude_fields()
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'place_id' => 260029001,
                'lat' => '51.51',
                'lon' => '-0.09',
                'display_name' => 'London',
                'osm_id' => '123',  // Should be excluded by default
                'address' => ['city' => 'London'],
            ]),
        ]);

        $response = $this->postJson('/cp/simple-address/reverse', [
            'lat' => '51.51',
            'lon' => '-0.09',
            'provider' => 'nominatim',
            'language' => 'en',
            'additional_exclude_fields' => [],
        ]);

        $response->assertSuccessful();
        $this->assertArrayNotHasKey('osm_id', $response['results'][0]);
    }

    public function test_reverse_geocoding_applies_additional_exclude_fields()
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'place_id' => 260029001,
                'lat' => '51.51',
                'lon' => '-0.09',
                'display_name' => 'London',
                'address' => ['city' => 'London', 'postcode' => 'SW1A1AA'],
            ]),
        ]);

        $response = $this->postJson('/cp/simple-address/reverse', [
            'lat' => '51.51',
            'lon' => '-0.09',
            'provider' => 'nominatim',
            'language' => 'en',
            'additional_exclude_fields' => ['place_id'],
        ]);

        $response->assertSuccessful();
        $this->assertArrayNotHasKey('place_id', $response['results'][0]);
    }

    public function test_reverse_geocoding_caches_results()
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'place_id' => 260029001,
                'lat' => '51.51',
                'lon' => '-0.09',
                'display_name' => 'London',
                'address' => ['city' => 'London'],
            ]),
        ]);

        // First request
        $response1 = $this->postJson('/cp/simple-address/reverse', [
            'lat' => '51.51',
            'lon' => '-0.09',
            'provider' => 'nominatim',
            'language' => 'en',
            'additional_exclude_fields' => [],
        ]);

        $response1->assertHeader('X-Cache', 'MISS');

        // Second request (same coords)
        $response2 = $this->postJson('/cp/simple-address/reverse', [
            'lat' => '51.51',
            'lon' => '-0.09',
            'provider' => 'nominatim',
            'language' => 'en',
            'additional_exclude_fields' => [],
        ]);

        $response2->assertHeader('X-Cache', 'HIT');
    }

    public function test_reverse_geocoding_api_failure_returns_error()
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([], 502),
        ]);

        $response = $this->postJson('/cp/simple-address/reverse', [
            'lat' => '51.51',
            'lon' => '-0.09',
            'provider' => 'nominatim',
            'language' => 'en',
            'additional_exclude_fields' => [],
        ]);

        $response->assertStatus(502)
            ->assertJsonPath('message', 'Provider API request failed');
    }

    public function test_reverse_geocoding_with_missing_api_key_returns_error()
    {
        $response = $this->postJson('/cp/simple-address/reverse', [
            'lat' => '51.51',
            'lon' => '-0.09',
            'provider' => 'geoapify',  // Requires API key
            'language' => 'en',
            'additional_exclude_fields' => [],
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('message', fn ($msg) => str_contains($msg, 'API key'));
    }

    public function test_reverse_geocoding_with_language_parameter()
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'place_id' => 260029001,
                'lat' => '51.51',
                'lon' => '-0.09',
                'display_name' => 'Londres',
                'address' => [],
            ]),
        ]);

        $response = $this->postJson('/cp/simple-address/reverse', [
            'lat' => '51.51',
            'lon' => '-0.09',
            'provider' => 'nominatim',
            'language' => 'fr',
            'additional_exclude_fields' => [],
        ]);

        $response->assertSuccessful();
        // Verify the request included language parameter
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'accept-language=fr');
        });
    }

    public function test_reverse_geocoding_wraps_single_result_in_array()
    {
        // Nominatim returns single object, should be wrapped for transformer
        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'place_id' => 260029001,
                'lat' => '51.51',
                'lon' => '-0.09',
                'display_name' => 'London',
                'address' => ['city' => 'London'],
            ]),
        ]);

        $response = $this->postJson('/cp/simple-address/reverse', [
            'lat' => '51.51',
            'lon' => '-0.09',
            'provider' => 'nominatim',
            'language' => 'en',
            'additional_exclude_fields' => [],
        ]);

        // Should return array of results (even though API returned single object)
        $response->assertSuccessful()
            ->assertJsonIsArray('results')
            ->assertJsonCount(1, 'results');
    }
}
