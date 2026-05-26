<?php

declare(strict_types=1);

namespace Domain\Shared\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Regra de validação que verifica se um registro existe dentro do escopo do tenant autenticado.
 *
 * Utilizada para validar foreign keys em campos de formulário, garantindo que
 * o recurso referenciado pertence ao mesmo tenant do usuário logado.
 */
class TenantExistsRule implements ValidationRule
{
    /**
     * @param  string  $table  Tabela a verificar no banco de dados.
     * @param  string  $column  Coluna a comparar com o valor informado (padrão: 'id').
     */
    public function __construct(
        private readonly string $table,
        private readonly string $column = 'id',
    ) {}

    /**
     * Valida que o valor existe na tabela dentro do tenant autenticado.
     *
     * @param  string  $attribute  Nome do campo sendo validado.
     * @param  mixed  $value  Valor fornecido pelo usuário.
     * @param  Closure  $fail  Callback para disparar falha de validação.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->column === 'id' && (! is_string($value) || ! Str::isUuid($value))) {
            return;
        }

        $exists = DB::table($this->table)
            ->where($this->column, $value)
            ->where('tenant_id', auth()->user()->tenant_id)
            ->exists();

        if (! $exists) {
            $fail('validation.exists')->translate();
        }
    }
}
