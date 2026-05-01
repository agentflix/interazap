<?php

/**
 * Helpers compartilhados para trilha E2E do Autopilot.
 *
 * Uso: require __DIR__ . '/helpers.php';
 */

declare(strict_types=1);

use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\Ai\Services\ToolDispatcherService;

// ─── Estado global ─────────────────────────────────────────────────────────

$E2E_RESULTS = [];
$E2E_PASS    = 0;
$E2E_FAIL    = 0;

// ─── Funções de execução ────────────────────────────────────────────────────

/**
 * Executa um cenário de teste capturando exceções e marcando PASS/FAIL.
 */
function e2e_run(string $label, Closure $fn): array
{
    global $E2E_RESULTS, $E2E_PASS, $E2E_FAIL;

    $start = microtime(true);

    try {
        $fn();
        $ms      = round((microtime(true) - $start) * 1000);
        $result  = ['label' => $label, 'pass' => true, 'error' => null, 'ms' => $ms];
        $E2E_PASS++;
        echo "  \033[32m[PASS]\033[0m {$label} ({$ms}ms)\n";
    } catch (\Throwable $e) {
        $ms      = round((microtime(true) - $start) * 1000);
        $result  = ['label' => $label, 'pass' => false, 'error' => $e->getMessage(), 'ms' => $ms];
        $E2E_FAIL++;
        echo "  \033[31m[FAIL]\033[0m {$label} ({$ms}ms)\n";
        echo "         → {$e->getMessage()}\n";
    }

    $E2E_RESULTS[] = $result;

    return $result;
}

/**
 * Assertion que lança RuntimeException em caso de falha.
 */
function e2e_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new \RuntimeException("Assert falhou: {$message}");
    }
}

/**
 * Despacha uma tool via ToolDispatcherService.
 *
 * @param  array<string, mixed>  $params
 * @param  array<string, mixed>  $ctx
 */
function e2e_dispatch(string $tool, array $params, array $ctx): ToolResultDTO
{
    $dispatcher = app(ToolDispatcherService::class);

    return $dispatcher->dispatch($tool, $params, $ctx);
}

/**
 * Imprime o sumário final de um grupo de testes.
 */
function e2e_summary(string $group, int $pass, int $fail): void
{
    $total = $pass + $fail;
    $color = $fail > 0 ? "\033[31m" : "\033[32m";
    echo "\n  {$color}▶ {$group}: {$pass}/{$total} passou\033[0m\n";
}

/**
 * Imprime separador de grupo.
 */
function e2e_group(string $name): void
{
    echo "\n\033[1;34m=== {$name} ===\033[0m\n";
}

/**
 * Imprime sumário global (chamado no run.php).
 */
function e2e_global_summary(): void
{
    global $E2E_PASS, $E2E_FAIL;
    $pass  = (int) $E2E_PASS;
    $fail  = (int) $E2E_FAIL;
    $total = $pass + $fail;
    echo "\n";
    echo str_repeat('─', 50) . "\n";

    if ($fail === 0) {
        echo "\033[32m✓ TODOS OS TESTES PASSARAM: {$pass}/{$total}\033[0m\n";
    } else {
        echo "\033[31m✗ FALHAS: {$fail}/{$total} | PASSOU: {$pass}/{$total}\033[0m\n";
    }

    echo str_repeat('─', 50) . "\n";
}
