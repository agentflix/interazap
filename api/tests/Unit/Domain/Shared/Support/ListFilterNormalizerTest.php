<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\Support;

use Domain\Shared\Support\ListFilterNormalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListFilterNormalizerTest extends TestCase
{
    #[Test]
    public function it_normalizes_is_active_string_values(): void
    {
        $this->assertTrue(ListFilterNormalizer::normalizeIsActive('active'));
        $this->assertTrue(ListFilterNormalizer::normalizeIsActive('true'));
        $this->assertTrue(ListFilterNormalizer::normalizeIsActive('1'));

        $this->assertFalse(ListFilterNormalizer::normalizeIsActive('inactive'));
        $this->assertFalse(ListFilterNormalizer::normalizeIsActive('false'));
        $this->assertFalse(ListFilterNormalizer::normalizeIsActive('0'));
    }

    #[Test]
    public function it_supports_all_keyword_when_enabled(): void
    {
        $this->assertNull(ListFilterNormalizer::normalizeIsActive('all', true));
        $this->assertSame('all', ListFilterNormalizer::normalizeIsActive('all'));
    }

    #[Test]
    public function it_keeps_boolean_values_for_is_active(): void
    {
        $this->assertTrue(ListFilterNormalizer::normalizeIsActive(true));
        $this->assertFalse(ListFilterNormalizer::normalizeIsActive(false));
    }

    #[Test]
    public function it_normalizes_sort_by_against_allowed_values(): void
    {
        $allowedSort = ['name', 'created_at', 'updated_at', 'is_active'];

        $this->assertSame('name', ListFilterNormalizer::normalizeSortBy('name', $allowedSort, 'created_at'));
        $this->assertSame('created_at', ListFilterNormalizer::normalizeSortBy('unknown', $allowedSort, 'created_at'));
        $this->assertSame('created_at', ListFilterNormalizer::normalizeSortBy(null, $allowedSort, 'created_at'));
    }

    #[Test]
    public function it_normalizes_sort_direction(): void
    {
        $this->assertSame('asc', ListFilterNormalizer::normalizeSortDirection('asc'));
        $this->assertSame('desc', ListFilterNormalizer::normalizeSortDirection('DESC'));
        $this->assertSame('asc', ListFilterNormalizer::normalizeSortDirection('invalid'));
    }

    #[Test]
    public function it_normalizes_per_page_with_default_and_max(): void
    {
        $this->assertSame(15, ListFilterNormalizer::normalizePerPage(null));
        $this->assertSame(15, ListFilterNormalizer::normalizePerPage(0));
        $this->assertSame(25, ListFilterNormalizer::normalizePerPage(25));
        $this->assertSame(100, ListFilterNormalizer::normalizePerPage(120, 15, 100));
    }
}
