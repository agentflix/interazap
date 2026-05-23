<?php

declare(strict_types=1);

namespace Domain\Shared\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class TenantExistsRule implements ValidationRule
{
    public function __construct(
        private readonly string $table,
        private readonly string $column = 'id',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = DB::table($this->table)
            ->where($this->column, $value)
            ->where('tenant_id', auth()->user()->tenant_id)
            ->exists();

        if (! $exists) {
            $fail('validation.exists')->translate();
        }
    }
}
