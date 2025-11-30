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
        $data = array_filter($this->resource->toArray(), fn ($v) => $v !== null && $v !== '');

        $output = array_filter([
            'label' => $this->formatLabel(),
            'lat' => (string) $this->getCoordinates()->getLatitude(),
            'lon' => (string) $this->getCoordinates()->getLongitude(),
            ...$data,
        ], fn ($v) => $v !== null && $v !== '');

        return Arr::except($output, $exclude);
    }

    private function formatLabel(): string
    {
        $parts = array_filter([
            $this->getStreetNumber(),
            $this->getStreetName(),
            $this->getLocality(),
            $this->getAdminLevels()->first()?->getName(),
            $this->getCountry()?->getName(),
        ]);

        return implode(', ', $parts);
    }
}
