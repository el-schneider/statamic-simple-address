<?php

use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\User;

/**
 * Creates a super user, a collection whose blueprint holds a single simple_address
 * field with the given config, and one entry holding a real search result, so the
 * stored value has the shape the fieldtype actually produces.
 *
 * Returns the control panel edit URL of that entry.
 */
function seedZoomEntry(object $test, string $collectionHandle, array $fieldConfig = []): string
{
    $user = User::make()
        ->email("admin+{$collectionHandle}@test.com")
        ->password('password')
        ->makeSuper();

    $user->save();

    Collection::make($collectionHandle)
        ->title(ucfirst($collectionHandle))
        ->save();

    Blueprint::makeFromFields([
        'address' => array_merge(['type' => 'simple_address'], $fieldConfig),
    ])
        ->setHandle('default')
        ->setNamespace('collections/'.$collectionHandle)
        ->save();

    $test->actingAs($user);

    $address = $test->post('/cp/simple-address/search', ['query' => '123 Main'])
        ->assertOk()
        ->json('results.0');

    $entry = Entry::make()
        ->collection($collectionHandle)
        ->slug('first')
        ->published(true)
        ->data([
            'title' => 'First',
            'address' => $address,
        ]);

    $entry->save();

    return "/cp/collections/{$collectionHandle}/entries/{$entry->id()}";
}

/**
 * Reads the zoom level Leaflet actually rendered with, taken from the {z} segment
 * of the first tile URL. Tiles are created with their computed URL even when the
 * request itself never completes, so this works without network access.
 */
function readRenderedZoom(): string
{
    return '(async () => {
        const delay = (ms) => new Promise((r) => setTimeout(r, ms));

        for (let i = 0; i < 40; i++) {
            const tile = document.querySelector("img.leaflet-tile");
            const match = tile?.getAttribute("src")?.match(/cartocdn\.com\/[a-z_]+\/(\d+)\//);

            if (match) {
                return Number(match[1]);
            }

            await delay(250);
        }

        return null;
    })()';
}

it('opens the map at zoom 13 when no zoom is configured', function () {
    $editUrl = seedZoomEntry($this, 'defaultzoom');

    visit($editUrl)
        ->assertPresent('.simple-address-field')
        ->click('internal:text="Show details"i')
        ->assertScript(readRenderedZoom(), 13);
});

it('opens the map at the configured zoom', function () {
    $editUrl = seedZoomEntry($this, 'customzoom', ['zoom' => 9]);

    visit($editUrl)
        ->assertPresent('.simple-address-field')
        ->click('internal:text="Show details"i')
        ->assertScript(readRenderedZoom(), 9);
});

it('treats a configured zoom of 0 as the world view rather than a missing value', function () {
    $editUrl = seedZoomEntry($this, 'zerozoom', ['zoom' => 0]);

    visit($editUrl)
        ->assertPresent('.simple-address-field')
        ->click('internal:text="Show details"i')
        ->assertScript(readRenderedZoom(), 0);
});
