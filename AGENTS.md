# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Statamic Simple Address**. A simple address autocomplete fieldtype for Statamic v6. Works out of the box with Nominatim (OpenStreetMap) and supports 30+ geocoding providers via geocoder-php.

## Development Commands

### Code Quality

```bash
prettier --check .
prettier --write .
./vendor/bin/pint --test
./vendor/bin/pint
```

### Testing

```bash
./vendor/bin/pest
./vendor/bin/pest --filter=SomeTest
```

### Integration Testing with Live App

A full Laravel test app is available at `../statamic-simple-address-v6` and can be accessed at `http://statamic-simple-address-v6.test`.

**Credentials:**

- Email: `agent@agent.md`
- Password: `agent`
- Login URL: `http://statamic-simple-address-v6.test/cp`
- Test Page URL: `http://statamic-simple-address-v6.test/cp/collections/pages/entries/4e8a2abf-764e-4001-b57f-2de19029f358`

For programmatic testing, use your agent-browser skill or curl with session cookies.

See logs at `../statamic-simple-address-v6/storage/logs/laravel.log` when debugging.

## Off-Limits Files

- **`resources/dist/`** - Built by CI. Do NOT run `npm run build`.
- **`CHANGELOG.md`** - Updated by CI on release. Do NOT edit.
