# Geocoding Service Simplification Design

## Overview

This design consolidates the geocoding abstraction by adopting the [Geocoder library](https://github.com/geocoder-php/Geocoder) as the foundational provider abstraction. It replaces custom provider logic with a thin, portable service layer. The addon adopts a single global geocoding provider, simplifying both controller logic and fieldtype configuration.

## Problem

The current architecture maintains duplicate validation and error handling logic across the controller and fixture generator. The SimpleAddress fieldtype presents a provider selector dropdown despite no real use case for switching providers at runtime. Custom provider abstraction duplicates functionality already provided by the Geocoder library.

## Solution Architecture

### GeocodingService (Portable)

A thin wrapper around the Geocoder library that abstracts provider initialization and API calls.

**Responsibilities:**

- Initialize the configured Geocoder provider
- Execute search queries
- Execute reverse geocoding queries
- Throw domain exceptions on failure

**Does NOT:**

- Validate request parameters (validation happens in controller)
- Filter results (filtering happens in controller)
- Handle HTTP responses (that's the controller's job)
- Depend on Laravel infrastructure

**Public Interface:**

```php
public function search(string $query, array $options = []): Collection
public function reverse(float $lat, float $lon, array $options = []): Collection
```

Both methods return Geocoder's `Collection` of normalized `Address` objects.

**Exceptions:**

- `GeocodingApiException` - Provider API failed (network, timeout, API error)
- `InvalidArgumentException` - Missing or invalid provider configuration
- `ConfigurationException` - Missing API keys

### GeocodingController (Simplified)

The controller validates requests and transforms Geocoder results into application-specific data.

**Responsibilities:**

- Validate request parameters (query, countries, language)
- Call GeocodingService methods
- Catch exceptions and translate to HTTP responses
- Filter Geocoder results to only fields needed for storage
- Return JSON response

**Flow:**

1. Validate `query`, `countries`, `language` from request
2. Call service with validated inputs
3. Receive Geocoder's `Address` collection
4. Filter each address to minimal stored fields
5. Return JSON

**No provider parameter:** Uses global provider from `config('simple-address.provider')`.

### SimpleAddress Fieldtype

The fieldtype simplifies by removing provider selection.

**Changes:**

- Remove provider dropdown selector
- Keep country and language field-level configuration
- Both use the global configured provider

The fieldtype remains focused on field configuration, not provider selection.

### Fixture Generator

Generates raw Geocoder results as test fixtures for all available providers.

**Unchanged responsibility:** Iterate through all providers and generate fixtures.

**Change:** Saves raw Geocoder results (not filtered), so test fixtures can verify the complete provider responses.

**Behavior:** Catches exceptions silently and continues on provider failures (current behavior maintained).

## Data Flow

### Search Request

1. Frontend sends query, countries, language
2. Controller validates these inputs
3. Controller calls `GeocodingService::search($query, ['countries' => [...]])`
4. Service initializes Geocoder with global provider, executes search
5. Service returns Geocoder `Collection` of `Address` objects
6. Controller filters each address (removes provider-specific noise)
7. Controller returns filtered results as JSON

### Reverse Geocoding

Same flow, starting with `GeocodingService::reverse($lat, $lon)`.

### Fixture Generation

1. Fixture generator iterates all providers
2. For each provider: temporarily sets it as active, calls service
3. Service returns raw Geocoder `Address` collection
4. Generator saves raw collection as JSON fixture
5. On exception: log and continue with next provider

## Benefits

- **Simpler controller**: No provider parameter, validation only, filtering is the remaining concern
- **Portable service**: Uses Geocoder, not Laravel facades; works outside Laravel context
- **Reduced configuration**: One global provider instead of per-field selection
- **Clear responsibility**: Service provides data, controller transforms it for storage
- **Testable**: Raw fixtures let tests verify complete provider responses

## Implementation Notes

- Geocoder library must be added as a dependency
- Provider configuration moves from custom classes to Geocoder's provider abstraction
- API key handling delegates to Geocoder's provider initialization
- Result transformation (the `transformResponse` pattern) moves to controller filtering logic
