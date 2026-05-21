<?php

/**
 * Master runner — trilha E2E completa do módulo Autopilot.
 *
 * Execução:
 *   php artisan tinker --execute="require base_path('tests/E2E/Autopilot/run.php');"
 *
 * Ou via shell script:
 *   bash tests/E2E/run-e2e.sh
 */

declare(strict_types=1);

$startTime = microtime(true);

require_once __DIR__.'/helpers.php';

echo "\n";
echo "\033[1;36m╔══════════════════════════════════════════════════════╗\033[0m\n";
echo "\033[1;36m║     INTERAZAP — TRILHA E2E AUTOPILOT MODULE          ║\033[0m\n";
echo "\033[1;36m╚══════════════════════════════════════════════════════╝\033[0m\n";
echo '  Data: '.date('Y-m-d H:i:s')."\n";
echo '  Env:  '.(app()->environment())."\n";

$scripts = [
    'test-01-chat',
    'test-02-contacts',
    'test-03-companies',
    'test-04-negotiations',
    'test-05-proposals',
    'test-06-knowledge',
    'test-07-scheduling',
    'test-08-tasks-notes',
    'test-09-notify',
    'test-10-delegation',
    'test-11-permissions',
    'test-12-full-flow',
    'test-13-chat-simulation',
    'test-14-chat-fullstack',
];

foreach ($scripts as $script) {
    require __DIR__."/{$script}.php";
}

// Teardown sempre executa, independente de falhas
require __DIR__.'/teardown.php';

// Sumário global
e2e_global_summary();

$elapsed = round(microtime(true) - $startTime, 2);
echo "  Tempo total: {$elapsed}s\n\n";
