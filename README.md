# Statamic Simple Address

Statamic Simple Address does what is says. It gives you an address autocomplete with simplicity in mind.

- It uses the Open Source [Nominatim](https://nominatim.org/) geocoding service, so there is no need to set-up an API-Key.
- It also strips a lot of fields from the API by default, as it tries to keep things simple

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

## Thanks to

- [Matt Rothenberg](https://github.com/mattrothenberg) for his work on [Statamic Mapbox Address](https://github.com/mattrothenberg/statamic-mapbox-address)
- [Nominatim](https://nominatim.org/) for their great service
