<?php

namespace ElSchneider\StatamicSimpleAddress\Fieldtypes;

use Statamic\Fields\Fieldtype;

class SimpleAddress extends Fieldtype
{
    protected $icon = 'pin';

    protected function configFieldItems(): array
    {
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
                'options' => array_combine($this->getAvailableProviders(), $this->getAvailableProviders()),
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
     * Get available geocoding providers
     */
    protected function getAvailableProviders(): array
    {
        return array_keys(config('simple-address.providers', []));
    }

    /**
     * Get the active provider name for this field
     */
    protected function getActiveProvider(): string
    {
        // Use field-level config if set, otherwise use app default
        if (! empty($this->config('provider'))) {
            return $this->config('provider');
        }

        return config('simple-address.default_provider', 'nominatim');
    }

    /**
     * Get the full configuration for the active provider
     */
    protected function getProviderConfig(): array
    {
        $provider = $this->getActiveProvider();
        $config = config("simple-address.providers.{$provider}");

        if (! $config) {
            throw new \InvalidArgumentException(
                "Geocoding provider '{$provider}' not found in simple-address config"
            );
        }

        return $config;
    }

    /**
     * Pre-process the fieldtype config before sending to the frontend
     *
     * @return array
     */
    public function preload()
    {
        $provider = $this->getActiveProvider();
        $providerConfig = $this->getProviderConfig();

        // Get default exclusions for this provider
        $defaultExclusions = $providerConfig['default_exclude_fields'] ?? [];

        // Get field-level additional exclusions
        $fieldLevelExclusions = $this->config('additional_exclude_fields') ?? [];

        // Merge them
        $allExclusions = array_unique(array_merge($defaultExclusions, $fieldLevelExclusions));

        return [
            'provider_config' => $providerConfig,
            'provider' => $provider,
            'provider_min_debounce_delay' => $providerConfig['min_debounce_delay'] ?? 0,
            'default_exclude_fields' => $defaultExclusions,
            'additional_exclude_fields' => $fieldLevelExclusions,
            'all_exclude_fields' => $allExclusions,
        ];
    }

    /**
     * The blank/default value.
     *
     * @return array|null
     */
    public function defaultValue()
    {
        return null;
    }

    /**
     * Pre-process the data before it gets sent to the publish page.
     *
     * @param  mixed  $data
     * @return array|mixed
     */
    public function preProcess($data)
    {
        return $data;
    }

    /**
     * Process the data before it gets saved.
     *
     * @param  mixed  $data
     * @return array|mixed
     */
    public function process($data)
    {
        return $data;
    }
}
