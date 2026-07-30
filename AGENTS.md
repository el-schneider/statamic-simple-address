## Project Overview

**Statamic Simple Address**. A simple address autocomplete fieldtype for Statamic v6. Works out of the box with Nominatim (OpenStreetMap) and supports 30+ geocoding providers via geocoder-php.

## Development Commands

### Code Quality

```bash
npm run check   # prettier --check, eslint, pint --test
npm run fix     # the same three, writing
```

### Testing

```bash
./vendor/bin/pest
./vendor/bin/pest --filter=SomeTest
```

CI runs `pest --ci --exclude-group=browser`. Everything under `tests/Browser/` is in that group, so those tests never run in CI.

### Integration Testing

Verifying fieldtype changes in a browser needs a Statamic app with this addon installed as a path repository. Build the assets, publish them into the app (`vendor:publish --tag=statamic-simple-address --force`), and check the control panel.

## Contributing

- Comments say why, not what changed. History belongs in the PR.
- UI changes: verify in a real browser (agent-browser, Chrome DevTools) and say what you checked. No browser automation available — ask, don't guess.
- Add nothing you can derive or reuse.
- Fix the cause, not the reported symptom.
- No abstraction with a single caller.
- Let failures surface. No try/catch for tidiness.

## Off-Limits Files

- **`resources/dist/`** — Built by CI on push to `main`. Do NOT commit build output.
- **`CHANGELOG.md`** — Updated by CI on release. Do NOT edit.
