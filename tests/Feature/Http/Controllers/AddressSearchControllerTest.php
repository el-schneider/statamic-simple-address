<?php

use Illuminate\Support\Facades\Http;
use Statamic\Facades\User;

beforeEach(function () {
    $user = User::make()->email('test@example.com')->id('test-user')->makeSuper();
    $user->save();
    $this->actingAs($user);

    Http::fake([
        'https://nominatim.openstreetmap.org/search*' => Http::response([
            [
                'osm_id' => 12345,
                'lat' => '48.8534951',
                'lon' => '2.3483915',
                'name' => 'Paris',
                'display_name' => 'Paris, Île-de-France, France',
                'address' => [
                    'city' => 'Paris',
                    'state' => 'Île-de-France',
                    'country' => 'France',
                    'country_code' => 'fr',
                ],
            ],
        ]),
    ]);
});

test('search returns normalized response', function () {
    $response = $this->postJson('/cp/simple-address/search', [
        'query' => 'Paris',
        'provider' => 'nominatim',
    ]);

    $response->assertStatus(200);

    $results = $response->json('results');
    expect($results)->toHaveCount(1);
    expect($results[0]['id'])->toBe('12345');
    expect($results[0]['label'])->toBe('Paris, Île-de-France, France');
    expect($results[0]['lat'])->toBe('48.8534951');
    expect($results[0]['lon'])->toBe('2.3483915');
    expect($results[0]['name'])->toBe('Paris');
    expect($results[0]['city'])->toBe('Paris');
    expect($results[0]['region'])->toBe('Île-de-France');
    expect($results[0]['country'])->toBe('France');
    expect($results[0]['countryCode'])->toBe('FR');
});

test('search with invalid provider returns error', function () {
    $response = $this->postJson('/cp/simple-address/search', [
        'query' => 'Paris',
        'provider' => 'nonexistent',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['provider']);
});

test('search with empty query returns error', function () {
    $response = $this->postJson('/cp/simple-address/search', [
        'query' => '',
        'provider' => 'nominatim',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['query']);
});

test('identical requests are cached', function () {
    $response1 = $this->postJson('/cp/simple-address/search', [
        'query' => 'Paris',
        'provider' => 'nominatim',
    ]);

    expect($response1->status())->toBe(200);
    Http::assertSentCount(1);

    $response2 = $this->postJson('/cp/simple-address/search', [
        'query' => 'Paris',
        'provider' => 'nominatim',
    ]);

    expect($response2->status())->toBe(200);
    Http::assertSentCount(1);
    expect($response2->json())->toBe($response1->json());
});

test('search with missing API key returns helpful error', function () {
    $response = $this->postJson('/cp/simple-address/search', [
        'query' => 'London',
        'provider' => 'geoapify',
    ]);

    $response->assertStatus(400);
    expect($response->json('message'))->toContain('API key');
});
