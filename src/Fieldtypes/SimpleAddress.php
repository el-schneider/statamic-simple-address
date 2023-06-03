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
                'instructions' => __('Exlude fields from being saved, to keep things **simple**.'),
                'width' => 50,
                'default' => ['boundingbox', 'class', 'display_name', 'icon', 'importance', 'licence', 'osm_id', 'osm_type', 'place_id'],
            ],
        ];
    }

    /**
     * The blank/default value.
     *
     * @return array
     */
    public function defaultValue()
    {
        return null;
    }

    /**
     * Pre-process the data before it gets sent to the publish page.
     *
     * @param mixed $data
     * @return array|mixed
     */
    public function preProcess($data)
    {
        return $data;
    }

    /**
     * Process the data before it gets saved.
     *
     * @param mixed $data
     * @return array|mixed
     */
    public function process($data)
    {
        return $data;
    }
}
