<?php

use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\User;

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
