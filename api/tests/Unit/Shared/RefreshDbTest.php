<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class RefreshDbTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_one(): void
    {
        $this->assertTrue(true);
    }
}
