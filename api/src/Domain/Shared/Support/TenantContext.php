<?php

declare(strict_types=1);

namespace Domain\Shared\Support;

/**
 * Gerenciador de contexto de tenant para o ciclo de vida de uma requisição.
 *
 * Mantém uma pilha de identificadores de tenant para suportar contextos
 * aninhados (ex: jobs internos) e um flag de super admin que desativa o
 * isolamento multi-tenant nas queries globais.
 */
final class TenantContext
{
    /**
     * @var array<int, string|null>
     */
    private static array $tenantStack = [];

    private static bool $superAdminContext = false;

    /**
     * Define o tenant corrente, substituindo toda a pilha de contexto.
     *
     * @param  string|null  $tenantId  Identificador do tenant ou null para limpar.
     */
    public static function set(?string $tenantId): void
    {
        self::$tenantStack = [$tenantId];
    }

    /**
     * Empilha um novo tenant sem descartar o contexto anterior.
     *
     * @param  string|null  $tenantId  Identificador do tenant a empilhar.
     */
    public static function push(?string $tenantId): void
    {
        self::$tenantStack[] = $tenantId;
    }

    /**
     * Remove o tenant mais recente da pilha, restaurando o contexto anterior.
     */
    public static function pop(): void
    {
        array_pop(self::$tenantStack);
    }

    /**
     * Limpa completamente a pilha de tenant e o flag de super admin.
     *
     * Deve ser chamado no bloco finally do middleware de contexto.
     */
    public static function clear(): void
    {
        self::$tenantStack = [];
        self::$superAdminContext = false;
    }

    /**
     * Retorna o identificador do tenant atualmente no topo da pilha.
     *
     * @return string|null Tenant corrente ou null se a pilha estiver vazia.
     */
    public static function get(): ?string
    {
        if (empty(self::$tenantStack)) {
            return null;
        }

        // Get the last element - guaranteed to exist since we checked empty
        /** @var string */
        $current = end(self::$tenantStack);

        return $current;
    }

    /**
     * Executa o callback no contexto do tenant informado, restaurando o anterior ao final.
     *
     * @template TReturn
     *
     * @param  string|null  $tenantId  Tenant a ativar durante a execução.
     * @param  callable():TReturn  $callback  Código a executar dentro do contexto.
     * @return TReturn Retorno do callback.
     */
    public static function run(?string $tenantId, callable $callback)
    {
        self::push($tenantId);

        try {
            return $callback();
        } finally {
            self::pop();
        }
    }

    /**
     * Habilita explicitamente o contexto de super admin, desativando isolamento de tenant.
     */
    public static function enableSuperAdminContext(): void
    {
        self::$superAdminContext = true;
    }

    /**
     * Desabilita o contexto de super admin, restaurando o isolamento de tenant.
     */
    public static function disableSuperAdminContext(): void
    {
        self::$superAdminContext = false;
    }

    /**
     * Indica se o escopo atual foi explicitamente marcado como super admin.
     *
     * @return bool Verdadeiro quando o contexto de super admin está ativo.
     */
    public static function isSuperAdminContext(): bool
    {
        return self::$superAdminContext;
    }

    /**
     * Executa o callback desativando o isolamento de tenant (contexto de super admin).
     *
     * Garante restauração do estado anterior via bloco finally.
     *
     * @template TReturn
     *
     * @param  callable():TReturn  $callback  Código a executar sem restrição de tenant.
     * @return TReturn Retorno do callback.
     */
    public static function runAsSuperAdmin(callable $callback)
    {
        $previous = self::$superAdminContext;
        self::$superAdminContext = true;

        try {
            return $callback();
        } finally {
            self::$superAdminContext = $previous;
        }
    }
}
