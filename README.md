# Statamic Simple Address

Statamic Simple Address does what is says. It gives you an address autocomplete with simplicity in mind.

- It uses the Open Source [Nominatim](https://nominatim.org/) geocoding service, so there is no need to set-up an API-Key.
- It also strips a lot of fields from the API by default, as it tries to keep things simple

## ⚠️ Important: Nominatim Usage Policy Compliance

**By using this addon, you are responsible for complying with the [Nominatim Usage Policy](https://operations.osmfoundation.org/policies/nominatim/). The author of this addon cannot and does not take any liability for policy violations.**

Please review the full policy for requirements on rate limiting, attribution, appropriate use, and data extraction limitations.

**For commercial applications or high-volume usage, consider using [commercial providers](https://wiki.openstreetmap.org/wiki/Nominatim#Alternatives_.2F_Third-party_providers) or [running your own Nominatim instance](https://nominatim.org/release-docs/latest/admin/Installation/).**

## Data

```yaml
simple_address_field:
  label: 'City of London, Greater London, England, United Kingdom'
  lat: '51.5156177'
  lon: '-0.0919983'
  display_name: 'City of London, Greater London, England, United Kingdom'
  type: administrative
  address:
    city: 'City of London'
    ISO3166-2-lvl6: GB-LND
    state_district: 'Greater London'
    state: England
    ISO3166-2-lvl4: GB-ENG
    country: 'United Kingdom'
    country_code: gb
  namedetails:
    name: 'City of London'
    alt_name: 'The City'
    ISO3166-2: GB-LND
    short_name: London
    official_name: 'City and County of the City of London'
```

## Configuration

The field supports several configuration options:

- **Placeholder**: Custom placeholder text for the search input
- **Countries**: Limit searches to specific countries (ISO 3166-1 alpha-2 codes)
- **Language**: Preferred language for search results (RFC2616 format)
- **Debounce Delay**: Search delay in milliseconds (minimum 1000ms for policy compliance)
- **Exclude Fields**: Fields to exclude from saved data

## License & Attribution

This addon uses data from OpenStreetMap via the Nominatim service. You must comply with:

- [OpenStreetMap Copyright](https://www.openstreetmap.org/copyright)
- [ODbL License](https://opendatacommons.org/licenses/odbl/)
- [Nominatim Usage Policy](https://operations.osmfoundation.org/policies/nominatim/)

## Thanks to

- [Matt Rothenberg](https://github.com/mattrothenberg) for his work on [Statamic Mapbox Address](https://github.com/mattrothenberg/statamic-mapbox-address)
- [Nominatim](https://nominatim.org/) for their great service
