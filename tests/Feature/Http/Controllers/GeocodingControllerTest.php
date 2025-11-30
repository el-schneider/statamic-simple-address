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
