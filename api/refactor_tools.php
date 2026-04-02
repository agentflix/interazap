<?php

$toolsDir = __DIR__.'/src/Domain/Ai/Tools';
$tools = glob($toolsDir.'/*.php');

$enumMapping = [];
$enumConstants = [];

foreach ($tools as $toolFile) {
    if (! is_file($toolFile)) {
        continue;
    }
    $content = file_get_contents($toolFile);
    if (preg_match("/public function getName\(\): string\s*\{\s*return '([^']+)';\s*\}/", $content, $matches)) {
        $stringName = $matches[1];
        // Convert string name to constant name, e.g., 'search_knowledge' -> 'SEARCH_KNOWLEDGE'
        $constName = strtoupper($stringName);
        $enumMapping[$stringName] = "self::$constName";
        $enumConstants[] = "    public const $constName = '$stringName';";

        // Replace in tool file definition
        $newContent = preg_replace(
            "/public function getName\(\): string\s*\{\s*return '([^']+)';\s*\}/",
            "public function getName(): string\n    {\n        return \\Domain\\Ai\\Enums\\AiToolEnum::$constName;\n    }",
            $content
        );
        file_put_contents($toolFile, $newContent);
    }
}

// Generate Enum File
$enumFileContent = "<?php\n\ndeclare(strict_types=1);\n\nnamespace Domain\\Ai\\Enums;\n\nclass AiToolEnum\n{\n".implode("\n", $enumConstants)."\n}\n";
file_put_contents(__DIR__.'/src/Domain/Ai/Enums/AiToolEnum.php', $enumFileContent);

// Replace in target files
$targetFiles = [
    __DIR__.'/src/Domain/Ai/Services/AiPermissionMatrixService.php',
    __DIR__.'/src/Domain/Platform/Services/PlatformTenantBootstrapCatalogService.php',
    __DIR__.'/tests/Feature/Platform/Actions/PlatformTenantBootstrapActionTest.php',
    __DIR__.'/tests/Feature/Platform/Services/PlatformTenantBootstrapCatalogServiceTest.php', // if exists
];

foreach ($targetFiles as $targetFile) {
    if (! file_exists($targetFile)) {
        continue;
    }
    $content = file_get_contents($targetFile);
    $modified = false;
    foreach ($enumMapping as $stringName => $enumRef) {
        $search = "'$stringName'";
        $replace = '\\Domain\\Ai\\Enums\\AiToolEnum'.substr($enumRef, 4);
        if (strpos($content, $search) !== false) {
            $content = str_replace($search, $replace, $content);
            $modified = true;
        }
    }
    if ($modified) {
        file_put_contents($targetFile, $content);
    }
}

echo "Refactoring completed.\n";
