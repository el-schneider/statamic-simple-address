<?php

namespace ElSchneider\StatamicSimpleAddress\Data;

readonly class AddressResult
{
    public function __construct(
        public string $label,
        public string $lat,
        public string $lon,
        public array $data = [],
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'label' => $this->label,
            'lat' => $this->lat,
            'lon' => $this->lon,
            ...$this->data,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
