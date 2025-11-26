<?php

namespace ElSchneider\StatamicSimpleAddress\Fieldtypes;

use ElSchneider\StatamicSimpleAddress\Services\ProviderApiService;
use Statamic\Fields\Fieldtype;

class SimpleAddress extends Fieldtype
{
    protected $icon = 'pin';

    protected function configFieldItems(): array
    {
        $providerService = app(ProviderApiService::class);

        return [
            'placeholder' => [
                'type' => 'text',
                'display' => __('Placeholder'),
                'default' => __('Start typing …'),
            ],
            'provider' => [
                'type' => 'select',
                'display' => __('Provider'),
                'instructions' => __('Choose the geocoding provider. Defaults to the app-wide setting if not specified.'),
                'options' => array_combine(
                    $providerService->getAvailableProviders(),
                    $providerService->getAvailableProviders()
                ),
                'width' => 50,
                'default' => config('simple-address.default_provider', 'nominatim'),
            ],
            'countries' => [
                'type' => 'taggable',
                'display' => __('Countries'),
                'instructions' => __('Change the countries to search in. Two letters country codes (ISO 3166-1 alpha-2). e.g. **gb** for the United Kingdom, **de** for Germany'),
                'width' => 50,
            ],
            'language' => [
                'type' => 'taggable',
                'display' => __('Language'),
                'instructions' => __('Preferred language order for showing search results. Either use a standard RFC2616 (e.g. **en**, **de-CH**, **en-US**) accept-language string or a simple comma-separated list of language codes.'),
                'width' => 50,
                'default' => ['en'],
            ],
            'debounce_delay' => [
                'type' => 'integer',
                'display' => __('Search Debounce Delay'),
                'instructions' => __('Delay in milliseconds before triggering search requests. This is a frontend optimization to reduce API calls while typing. The backend enforces each provider\'s minimum delay requirement automatically.'),
                'width' => 50,
                'default' => 300,
                'min' => 100,
                'max' => 2000,
                'required' => true,
            ],
            'additional_exclude_fields' => [
                'type' => 'taggable',
                'display' => __('Additional Exclude Fields'),
                'instructions' => __('Exclude additional fields from being saved, beyond the default exclusions. This keeps the stored data **simple** by only keeping essential information.'),
                'width' => 50,
            ],
        ];
    }

    /**
     * Get the active provider name for this field
     */
    protected function getActiveProvider(): string
    {
        if (! empty($this->config('provider'))) {
            return $this->config('provider');
        }

        return config('simple-address.default_provider', 'nominatim');
    }

    /**
     * Pre-process the fieldtype config before sending to the frontend
     */
    public function preload(): array
    {
        $providerService = app(ProviderApiService::class);
        $providerName = $this->getActiveProvider();
        $provider = $providerService->resolveProvider($providerName);

        $fieldLevelExclusions = $this->config('additional_exclude_fields') ?? [];
        $provider->setExcludeFields($fieldLevelExclusions);

        return [
            'provider' => $providerName,
            'provider_min_debounce_delay' => $provider->getMinDebounceDelay(),
            'default_exclude_fields' => $provider->getDefaultExcludeFields(),
            'additional_exclude_fields' => $fieldLevelExclusions,
            'all_exclude_fields' => $provider->getExcludeFields(),
        ];
    }

    /**
     * The blank/default value.
     */
    public function defaultValue(): ?array
    {
        return null;
    }

    /**
     * Pre-process the data before it gets sent to the publish page.
     */
    public function preProcess(mixed $data): mixed
    {
        return $data;
    }

    /**
     * Process the data before it gets saved.
     */
    public function process(mixed $data): mixed
    {
        return $data;
    }
}
