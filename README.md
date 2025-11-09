# Statamic Simple Address

Address autocomplete fieldtype for Statamic. Works out of the box with no API keys or configuration needed.

## Getting Started

Add a Simple Address field to your fieldtype. That's it. It uses Nominatim (free, open-source OpenStreetMap data) by default.

The field stores essential address information:

```yaml
simple_address_field:
  label: 'City of London, Greater London, England, United Kingdom'
  lat: '51.5156177'
  lon: '-0.0919983'
  display_name: 'City of London, Greater London, England, United Kingdom'
  type: administrative
  address: { ... }
  namedetails: { ... }
```

## Nominatim Usage Policy

Since Nominatim is the default provider, be aware of these requirements:

- **Rate limit**: 1 request per second (set debounce delay ≥ 1000ms in field config)
- **User-Agent**: Configure a User-Agent header identifying your application
- **Attribution**: Display OpenStreetMap attribution

See [Nominatim Usage Policy](https://operations.osmfoundation.org/policies/nominatim/) for full details.

## Using Different Providers

To switch providers or customize endpoints, publish the config:

```bash
php artisan vendor:publish --tag=simple-address-config
```

Edit `config/simple-address.php` to:

- **Switch globally**: Set `default_provider` or `SIMPLE_ADDRESS_PROVIDER` env var
- **Override per field**: Choose a different provider in the field configuration UI
- **Add custom providers**: Define new providers in the config file

Built-in providers: Nominatim, Geoapify, Geocodify. See the config file for examples of adding custom providers.

## Thanks to

- [Matt Rothenberg](https://github.com/mattrothenberg) for [Statamic Mapbox Address](https://github.com/mattrothenberg/statamic-mapbox-address)
- [Nominatim](https://nominatim.org/) team
