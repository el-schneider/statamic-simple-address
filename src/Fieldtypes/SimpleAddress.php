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
            'exclude_fields' => [
                'type' => 'taggable',
                'display' => __('Exclude Fields'),
                'instructions' => __('Fields to exclude from the address result. Supports dot notation for nested fields and wildcards (e.g. **data.street**, **data.**)'),
                'default' => ['bounds', 'adminLevels', 'providedBy'],
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
        return [];
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
