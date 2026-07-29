<?php

use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\User;

/**
 * Creates a super user, a collection whose blueprint holds a single simple_address
 * field with the given config, and one entry. The entry is seeded with a real
 * search result so the value shape matches what the fieldtype actually stores.
 *
 * Returns the control panel edit URL of that entry.
 */
function seedAddressEntry(object $test, string $collectionHandle, array $fieldConfig = [], bool $withValue = true): string
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

    $data = ['title' => 'First'];

    if ($withValue) {
        $data['address'] = $test->post('/cp/simple-address/search', ['query' => '123 Main'])
            ->assertOk()
            ->json('results.0');
    }

    $entry = Entry::make()
        ->collection($collectionHandle)
        ->slug('first')
        ->published(true)
        ->data($data);

    $entry->save();

    return "/cp/collections/{$collectionHandle}/entries/{$entry->id()}";
}

it('keeps the details panel collapsed when expand_details is off', function () {
    $editUrl = seedAddressEntry($this, 'collapsed');

    visit($editUrl)
        ->assertPresent('.simple-address-field')
        ->assertSee('Show details')
        ->assertDontSee('Hide details');
});

it('shows the details panel right away when expand_details is on', function () {
    $editUrl = seedAddressEntry($this, 'expanded', ['expand_details' => true]);

    visit($editUrl)
        ->assertPresent('.simple-address-field')
        ->assertSee('Hide details')
        ->assertPresent('.leaflet-container');
});

it('leaves the details panel open when the address is replaced', function () {
    $editUrl = seedAddressEntry($this, 'replaced', ['expand_details' => true], withValue: false);

    $input = '.simple-address-field input[type="search"]';

    visit($editUrl)
        // Without a value there is no toggle and no panel at all.
        ->assertDontSee('Hide details')
        ->click($input)
        ->typeSlowly($input, '123 Main', 50)
        ->assertScript('(async () => {
            const delay = (ms) => new Promise((r) => setTimeout(r, ms));

            for (let i = 0; i < 40; i++) {
                const items = Array.from(document.querySelectorAll("[data-ui-combobox-item]"));

                if (items.some((el) => (el.textContent || "").includes("Main Street"))) {
                    return true;
                }

                await delay(250);
            }

            return false;
        })()', true)
        ->click('internal:text="123, Main Street, London, England, United Kingdom"i')
        ->assertSee('Hide details')
        ->assertPresent('.leaflet-container');
});

it('lets you search and save a simple address in the control panel', function () {
    $user = User::make()
        ->email('admin@test.com')
        ->password('password')
        ->makeSuper();

    $user->save();

    expect(User::findByEmail('admin@test.com'))
        ->not->toBeNull();

    $collectionHandle = 'addresses';

    $collection = Collection::make($collectionHandle)
        ->title('Addresses');

    $collection->save();

    Blueprint::makeFromFields([
        'address' => [
            'type' => 'simple_address',
        ],
    ])
        ->setHandle('default')
        ->setNamespace('collections/'.$collectionHandle)
        ->save();

    $entry = Entry::make()
        ->collection($collectionHandle)
        ->slug('first')
        ->published(true)
        ->data([
            'title' => 'First',
        ]);

    $entry->save();

    $this->actingAs($user);
    $this->post('/cp/simple-address/search', ['query' => '123 Main'])
        ->assertOk()
        ->assertJsonPath('results.0.label', '123, Main Street, London, England, United Kingdom');

    $editUrl = "/cp/collections/{$collectionHandle}/entries/{$entry->id()}";

    $page = visit($editUrl)
        ->assertPresent('.simple-address-field');

    $input = '.simple-address-field input[type="search"]';

    expect($page->script('(async () => {
        const token = document.querySelector("meta[name=csrf-token]")?.getAttribute("content");

        const res = await fetch("/cp/simple-address/search", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": token,
            },
            body: JSON.stringify({ query: "123 Main" }),
        });

        if (!res.ok) {
            return `status:${res.status}`;
        }

        const data = await res.json();
        return data?.results?.[0]?.label ?? null;
    })()'))
        ->toBe('123, Main Street, London, England, United Kingdom');

    $page
        ->click($input)
        ->typeSlowly($input, '123 Main', 50)
        ->assertScript('(async () => {
            const delay = (ms) => new Promise((r) => setTimeout(r, ms));

            for (let i = 0; i < 40; i++) {
                const items = Array.from(document.querySelectorAll("[data-ui-combobox-item]"));

                if (items.some((el) => (el.textContent || "").includes("Main Street"))) {
                    return true;
                }

                await delay(250);
            }

            return false;
        })()', true)

        ->click('internal:text="123, Main Street, London, England, United Kingdom"i')
        ->pressAndWaitFor('Save & Publish', 2)
        ->navigate($editUrl)
        ->assertSee('123, Main Street, London, England, United Kingdom');
});
