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

        $user = User::make()->email('test@example.com')->id('test-user')->makeSuper();
        $user->save();
        $this->actingAs($user);
    }

    public function test_reverse_geocoding_returns_normalized_response()
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'osm_id' => 260029001,
                'lat' => '51.5153449',
                'lon' => '-0.0918298',
                'display_name' => 'City of London, Greater London, England, UK',
                'address' => [
                    'city' => 'City of London',
                    'state' => 'England',
                    'country' => 'United Kingdom',
                    'country_code' => 'gb',
                    'road' => 'Main Street',
                    'house_number' => '10',
                    'postcode' => 'EC2V 8AE',
                ],
            ]),
        ]);

        $response = $this->postJson('/cp/simple-address/reverse', [
            'lat' => '51.5153449',
            'lon' => '-0.0918298',
            'provider' => 'nominatim',
        ]);

        $response->assertSuccessful();

        $result = $response->json('results.0');
        $this->assertEquals('260029001', $result['id']);
        $this->assertEquals('City of London', $result['city']);
        $this->assertEquals('England', $result['region']);
        $this->assertEquals('United Kingdom', $result['country']);
        $this->assertEquals('GB', $result['countryCode']);
        $this->assertEquals('Main Street', $result['street']);
        $this->assertEquals('10', $result['houseNumber']);
        $this->assertEquals('EC2V 8AE', $result['postcode']);
    }

    public function test_reverse_geocoding_with_invalid_lat_returns_error()
    {
        $response = $this->postJson('/cp/simple-address/reverse', [
            'lat' => '91',
            'lon' => '-0.09',
            'provider' => 'nominatim',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('lat');
    }

    public function test_reverse_geocoding_with_invalid_lon_returns_error()
    {
        $response = $this->postJson('/cp/simple-address/reverse', [
            'lat' => '51.51',
            'lon' => '181',
            'provider' => 'nominatim',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('lon');
    }

    public function test_reverse_geocoding_with_invalid_provider_returns_error()
    {
        $response = $this->postJson('/cp/simple-address/reverse', [
            'lat' => '51.51',
            'lon' => '-0.09',
            'provider' => 'invalid_provider',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('provider');
    }

    public function test_reverse_geocoding_caches_results()
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'lat' => '51.51',
                'lon' => '-0.09',
                'display_name' => 'London',
                'address' => ['city' => 'London'],
            ]),
        ]);

        $response1 = $this->postJson('/cp/simple-address/reverse', [
            'lat' => '51.51',
            'lon' => '-0.09',
            'provider' => 'nominatim',
        ]);

        $response1->assertHeader('X-Cache', 'MISS');

        $response2 = $this->postJson('/cp/simple-address/reverse', [
            'lat' => '51.51',
            'lon' => '-0.09',
            'provider' => 'nominatim',
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
        ]);

        $response->assertStatus(502)->assertJsonPath('message', 'Provider API request failed');
    }

    public function test_reverse_geocoding_with_missing_api_key_returns_error()
    {
        $response = $this->postJson('/cp/simple-address/reverse', [
            'lat' => '51.51',
            'lon' => '-0.09',
            'provider' => 'geoapify',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('message', fn ($msg) => str_contains($msg, 'API key'));
    }

    public function test_reverse_geocoding_with_language_parameter()
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
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
        ]);

        $response->assertSuccessful();
        Http::assertSent(fn ($request) => str_contains($request->url(), 'accept-language=fr'));
    }

    public function test_reverse_geocoding_wraps_single_result_in_array()
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
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
        ]);

        $response->assertSuccessful()
            ->assertJsonIsArray('results')
            ->assertJsonCount(1, 'results');
    }
}
