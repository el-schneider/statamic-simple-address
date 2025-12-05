<?php

namespace ElSchneider\StatamicSimpleAddress\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $exclude = $request->input('exclude_fields', []);
        $data = $this->resource->toArray();
        $data = array_filter($data, fn ($v) => $v !== null && $v !== '');

        // Convert adminLevels keyed array to stdClass to preserve object structure in JSON
        if (isset($data['adminLevels']) && is_array($data['adminLevels'])) {
            $data['adminLevels'] = (object) $data['adminLevels'];
        }

        $output = [
            'label' => $this->formatLabel(),
            'lat' => (string) $this->getCoordinates()->getLatitude(),
            'lon' => (string) $this->getCoordinates()->getLongitude(),
        ];

        // Add remaining data, preserving keyed structures like adminLevels
        foreach ($data as $key => $value) {
            if (! in_array($key, ['label', 'latitude', 'longitude'], true)) {
                $output[$key] = $value;
            }
        }

        // Filter out null and empty values
        $output = array_filter($output, fn ($v) => $v !== null && $v !== '');

        return Arr::except($output, $exclude);
    }

    private function formatLabel(): string
    {
        $adminLevels = $this->getAdminLevels();
        $adminLevel = (count($adminLevels) > 0) ? $adminLevels->first() : null;

        $parts = array_filter([
            $this->getStreetNumber(),
            $this->getStreetName(),
            $this->getLocality(),
            $adminLevel?->getName(),
            $this->getCountry()?->getName(),
        ]);

        return implode(', ', $parts);
    }
}
