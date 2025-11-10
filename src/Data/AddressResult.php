<?php

namespace ElSchneider\StatamicSimpleAddress\Data;

readonly class AddressResult
{
    public function __construct(
        public string $label,
        public string $lat,
        public string $lon,
        public string $type,
        public string $name,
        public array $address,
        public array $additional = [],
    ) {}

    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'lat' => $this->lat,
            'lon' => $this->lon,
            'type' => $this->type,
            'name' => $this->name,
            'address' => $this->address,
            'additional' => $this->additional,
        ];
    }
}
