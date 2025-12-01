<?php

use Illuminate\Support\Facades\Route;

test('that api routes are registered', function () {
    expect(Route::has('statamic.cp.simple-address.search'))->toBeTrue();
    expect(Route::has('statamic.cp.simple-address.reverse'))->toBeTrue();
});

test('that the search endpoint returns a success response', function () {
    $this->actingAsSuperAdmin();

    $response = $this->post('/cp/simple-address/search', [
        'query' => '123 Main St',
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'results' => [
            '*' => [
                'label',
                'lat',
                'lon',
            ],
        ],
    ]);
});

test('that the search endpoint excludes fields using exclude_fields parameter', function () {
    $this->actingAsSuperAdmin();

    $response = $this->post('/cp/simple-address/search', [
        'query' => '123 Main St',
        'exclude_fields' => ['lon'],
    ]);

    $response->assertStatus(200);
    $data = $response->json('results.0');

    expect($data)->toHaveKeys(['label', 'lat']);
    expect($data)->not->toHaveKey('lon');
});

test('that the reverse endpoint excludes fields using exclude_fields parameter', function () {
    $this->actingAsSuperAdmin();

    $response = $this->post('/cp/simple-address/reverse', [
        'lat' => 51.5074,
        'lon' => -0.1278,
        'exclude_fields' => ['label'],
    ]);

    $response->assertStatus(200);
    $data = $response->json('results.0');

    expect($data)->toHaveKeys(['lat', 'lon']);
    expect($data)->not->toHaveKey('label');
});

test('that search endpoint response shape is correct', function () {
    $this->actingAsSuperAdmin();

    $response = $this->post('/cp/simple-address/search', [
        'query' => '123 Main St',
    ]);

    $response->assertStatus(200);
    $data = $response->json('results.0');

    // Verify top-level structure and required fields
    expect($data)->toHaveKeys(['label', 'lat', 'lon']);
    expect($data['label'])->toBeString();
    expect($data['label'])->toBe('123, Main Street, London, England, United Kingdom');
    expect($data['lat'])->toBeString();
    expect($data['lat'])->toBe('51.5074');
    expect($data['lon'])->toBeString();
    expect($data['lon'])->toBe('-0.1278');

    // Verify additional data fields from Location object
    expect($data)->toHaveKeys(['providedBy', 'locality', 'country', 'countryCode']);
    expect($data['providedBy'])->toBe('stub');
    expect($data['locality'])->toBe('London');
    expect($data['country'])->toBe('United Kingdom');
    expect($data['countryCode'])->toBe('GB');

    // Verify adminLevels is object keyed by level, not an indexed array
    expect($data)->toHaveKey('adminLevels');
    expect($data['adminLevels'])->toBeArray();
    expect($data['adminLevels'])->toHaveKey('1');
    $adminLevel = $data['adminLevels']['1'];
    expect($adminLevel)->toHaveKeys(['name', 'code', 'level']);
    expect($adminLevel['name'])->toBe('England');
    expect($adminLevel['code'])->toBe('GB-ENG');
    expect($adminLevel['level'])->toBe(1);

    // Verify other optional address fields
    expect($data)->toHaveKeys(['streetNumber', 'streetName', 'postalCode']);
    expect($data['streetNumber'])->toBe('123');
    expect($data['streetName'])->toBe('Main Street');
    expect($data['postalCode'])->toBe('SW1A 1AA');

    // Verify bounds structure
    expect($data)->toHaveKey('bounds');
    expect($data['bounds'])->toBeArray();
});
