<?php

declare(strict_types=1);

namespace Domain\Shared\Support;

/**
 * Simple context manager that stores the current tenant identifier.
 */
final class TenantContext
{
    /**
     * @var array<int, string|null>
     */
    private static array $tenantStack = [];

    private static bool $superAdminContext = false;

    /**
     * Replace the current context stack with a single tenant id.
     */
    public static function set(?string $tenantId): void
    {
        self::$tenantStack = [$tenantId];
    }

    /**
     * Push a tenant id keeping previous values in the stack.
     */
    public static function push(?string $tenantId): void
    {
        self::$tenantStack[] = $tenantId;
    }

    /**
     * Restore the previous tenant id from the stack.
     */
    public static function pop(): void
    {
        array_pop(self::$tenantStack);
    }

    /**
     * Clear all tenant context information.
     */
    public static function clear(): void
    {
        self::$tenantStack = [];
        self::$superAdminContext = false;
    }

    /**
     * Get the tenant currently in scope.
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
     * Execute the callback with the provided tenant context.
     *
     * @template TReturn
     *
     * @param  callable():TReturn  $callback
     * @return TReturn
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
     * Enable explicit super admin context.
     */
    public static function enableSuperAdminContext(): void
    {
        self::$superAdminContext = true;
    }

    /**
     * Disable explicit super admin context.
     */
    public static function disableSuperAdminContext(): void
    {
        self::$superAdminContext = false;
    }

    /**
     * Indicates if current scope was explicitly marked as super admin.
     */
    public static function isSuperAdminContext(): bool
    {
        return self::$superAdminContext;
    }

    /**
     * Execute callback bypassing tenant scope explicitly.
     *
     * @template TReturn
     *
     * @param  callable():TReturn  $callback
     * @return TReturn
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
