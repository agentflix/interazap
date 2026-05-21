<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(Tests\TestCase::class)->in('Feature', 'Unit');
uses(LazilyRefreshDatabase::class)->in('Feature', 'Unit');
