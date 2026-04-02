<?php

declare(strict_types=1);

use Domain\CRM\Services\CRMCsvParsingService;

it('detects semicolon delimiters automatically', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'csv');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary file');
    }

    file_put_contents($path, "name;email\nJohn;john@test.com\n");

    try {
        $service = app(CRMCsvParsingService::class);
        $delimiter = $service->detectDelimiter($path);

        expect($delimiter)->toBe(';');
    } finally {
        unlink($path);
    }
});

it('counts rows while ignoring headers and blank lines', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'csv');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary file');
    }

    file_put_contents($path, "name,email\nJohn,john@test.com\n\nJane,jane@test.com\n");

    try {
        $service = app(CRMCsvParsingService::class);
        $rows = $service->countRows($path, ',', true);

        expect($rows)->toBe(2);
    } finally {
        unlink($path);
    }
});

it('returns headers and sample rows from preview', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'csv');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary file');
    }

    file_put_contents($path, "name,email,phone\nJohn,john@test.com,123\nJane,jane@test.com,456\nBob,bob@test.com,789\n");

    try {
        $service = app(CRMCsvParsingService::class);
        $preview = $service->getPreview($path, ',', 2);

        expect($preview['header'])->toBe(['name', 'email', 'phone']);
        expect($preview['sample'])->toHaveCount(2);
        expect($preview['sample'][0])->toBe(['John', 'john@test.com', '123']);
    } finally {
        unlink($path);
    }
});

it('detects comma as default delimiter', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'csv');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary file');
    }

    file_put_contents($path, "name,email\nJohn,john@test.com\n");

    try {
        $service = app(CRMCsvParsingService::class);
        $delimiter = $service->detectDelimiter($path);

        expect($delimiter)->toBe(',');
    } finally {
        unlink($path);
    }
});

it('counts zero rows for empty file', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'csv');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary file');
    }

    file_put_contents($path, '');

    try {
        $service = app(CRMCsvParsingService::class);
        $rows = $service->countRows($path, ',', false);

        expect($rows)->toBe(0);
    } finally {
        unlink($path);
    }
});
