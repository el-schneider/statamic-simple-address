<?php

test('distribution assets are present and up to date', function () {
    $repoRoot = dirname(__DIR__, 2);
    $manifestPath = $repoRoot.'/resources/dist/build/manifest.json';

    expect(file_exists($manifestPath))->toBeTrue();

    $manifest = json_decode(file_get_contents($manifestPath), true);
    expect($manifest)->toBeArray();

    $entry = $manifest['resources/js/simple-address.js'] ?? null;
    expect($entry)->toBeArray();
    expect($entry['file'] ?? null)->toBeString();

    $jsPath = $repoRoot.'/resources/dist/build/'.$entry['file'];
    expect(file_exists($jsPath))->toBeTrue();

    foreach (($entry['css'] ?? []) as $cssFile) {
        $cssPath = $repoRoot.'/resources/dist/build/'.$cssFile;
        expect(file_exists($cssPath))->toBeTrue();
    }

    $js = file_get_contents($jsPath);
    expect($js)->toContain('globalProperties')->toContain('$axios');
});
