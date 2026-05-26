#!/usr/bin/env php
<?php

/**
 * PCI Lint Script — Previne exposição de dados de cartão em logs.
 *
 * Verifica se strings PAN/CVV aparecem em arquivos de log estruturado.
 * Falha o build se padrões proibidos forem encontrados.
 *
 * Uso: php scripts/check-pci-logs.php [--path=storage/logs]
 */

declare(strict_types=1);

$exitCode = 0;
$violations = [];

// Patterns that should NEVER appear in logs
$forbiddenPatterns = [
    '/\bcard_number\b/i' => 'card_number (PAN field name)',
    '/\bcardNumber\b/' => 'cardNumber (PAN field name)',
    '/\bcvv\b/i' => 'cvv (CVV field name)',
    '/\bcvc\b/i' => 'cvc (CVC field name)',
    '/\bccv\b/i' => 'ccv (CCV field name)',
    '/\bcreditCard\b(?!Token)/' => 'creditCard (raw card data — use creditCardToken instead)',
];

// Parse --path argument (default: storage/logs)
$logPath = 'storage/logs';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--path=')) {
        $logPath = substr($arg, 7);
    }
}

$basePath = dirname(__DIR__);
$fullLogPath = "{$basePath}/{$logPath}";

if (! is_dir($fullLogPath)) {
    echo "[PCI Lint] Log directory not found: {$fullLogPath}\n";
    echo "[PCI Lint] No logs to check — PASS.\n";
    exit(0);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($fullLogPath, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (! $file->isFile() || ! in_array($file->getExtension(), ['log', 'json'], true)) {
        continue;
    }

    $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        continue;
    }

    foreach ($lines as $lineNumber => $line) {
        foreach ($forbiddenPatterns as $pattern => $description) {
            if (preg_match($pattern, $line)) {
                $violations[] = sprintf(
                    '%s:%d — Found forbidden pattern "%s" in: %s',
                    $file->getPathname(),
                    $lineNumber + 1,
                    $description,
                    substr(trim($line), 0, 120)
                );
                $exitCode = 1;
            }
        }
    }
}

if ($exitCode === 0) {
    echo "[PCI Lint] PASS — No PAN/CVV patterns found in logs.\n";
} else {
    echo "[PCI Lint] FAIL — PCI violations found:\n";
    foreach ($violations as $violation) {
        echo "  - {$violation}\n";
    }
    echo "\n[PCI Lint] Fix: never log raw card data. Use card tokens instead.\n";
}

exit($exitCode);
