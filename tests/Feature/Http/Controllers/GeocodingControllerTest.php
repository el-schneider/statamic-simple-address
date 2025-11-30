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
        // 'additional_exclude_fields' => [],
        // 'countries' => [],
        // 'language' => 'en',
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
