<?php

declare(strict_types=1);

use Database\Seeders\PerformanceSeeder;

beforeEach(function (): void {
    // Truncate performance data before each test if needed
});

describe('PerformanceSeeder', function (): void {
    it('can be instantiated', function (): void {
        $seeder = new PerformanceSeeder;

        expect($seeder)->toBeInstanceOf(PerformanceSeeder::class);
    });

    it('generates random dates within expected ranges', function (): void {
        $dates = [];
        for ($i = 0; $i < 100; $i++) {
            $dates[] = PerformanceSeeder::randomDate();
        }

        $now = now();
        $oldest = $now->copy()->subYear();
        $newest = $now;

        foreach ($dates as $date) {
            expect($date)->toBeGreaterThanOrEqual($oldest);
            expect($date)->toBeLessThanOrEqual($newest);
        }
    });

    it('returns weighted random values according to distribution', function (): void {
        $weights = ['a' => 70, 'b' => 30];
        $results = ['a' => 0, 'b' => 0];

        for ($i = 0; $i < 1000; $i++) {
            $results[PerformanceSeeder::weightedRandom($weights)]++;
        }

        // 'a' should appear roughly 70% of the time
        expect($results['a'])->toBeGreaterThan(600);
        expect($results['b'])->toBeGreaterThan(200);
    });

    it('generates valid UUIDs', function (): void {
        $uuid = PerformanceSeeder::uuid();

        expect($uuid)->toBeString();
        expect(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid))->toBe(1);
    });

    it('has correct tenant profile distribution', function (): void {
        $reflection = new ReflectionClass(PerformanceSeeder::class);
        $profiles = $reflection->getConstant('TENANT_PROFILES');

        $counts = array_count_values($profiles);

        expect($counts['active'] ?? 0)->toBe(40);
        expect($counts['inactive'] ?? 0)->toBe(5);
        expect($counts['deleted'] ?? 0)->toBe(3);
        expect($counts['locked'] ?? 0)->toBe(2);
        expect(count($profiles))->toBe(50);
    });
});
