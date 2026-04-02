<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Database\Seeders\AuthPermissionSeeder;
use Tests\TestCase;

abstract class ReportsTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthPermissionSeeder::class);
    }
}
