<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Tests\TestCase;

final class AiToolsContractSanityTest extends TestCase
{
    public function test_all_tool_classes_follow_contract_and_schema_sanity(): void
    {
        $toolFiles = glob(base_path('src/Domain/Ai/Tools/*Tool.php')) ?: [];

        $this->assertNotEmpty($toolFiles, 'No AI tool classes were found under src/Domain/Ai/Tools.');

        foreach ($toolFiles as $toolFile) {
            $className = sprintf('Domain\\Ai\\Tools\\%s', basename($toolFile, '.php'));

            $this->assertTrue(class_exists($className), "Tool class {$className} does not exist.");
            $this->assertTrue(
                is_subclass_of($className, AiToolInterface::class),
                "Tool class {$className} must implement AiToolInterface.",
            );

            /** @var AiToolInterface $tool */
            $tool = app()->make($className);

            $toolName = trim($tool->getName());
            $toolDescription = trim($tool->getDescription());
            $parameters = $tool->getParameters();

            $this->assertNotSame('', $toolName, "Tool {$className} must return a non-empty name.");
            $this->assertNotSame('', $toolDescription, "Tool {$className} must return a non-empty description.");
            $this->assertIsArray($parameters, "Tool {$className} must return an array in getParameters().");

            foreach ($parameters as $parameterName => $definition) {
                $this->assertIsString($parameterName, "Tool {$className} has a non-string parameter key.");
                $this->assertTrue(
                    is_array($definition),
                    "Tool {$className} parameter {$parameterName} must be defined as an array.",
                );

                $type = trim((string) ($definition['type'] ?? ''));
                $this->assertNotSame('', $type, "Tool {$className} parameter {$parameterName} must define a type.");
                $this->assertArraySchemaHasItemsForArrayTypes($definition, "{$className}::{$parameterName}");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function assertArraySchemaHasItemsForArrayTypes(array $schema, string $context): void
    {
        $type = strtolower(trim((string) ($schema['type'] ?? '')));

        if ($type === 'array') {
            $this->assertArrayHasKey('items', $schema, "{$context} uses type=array and must define items.");
            $this->assertTrue(is_array($schema['items']), "{$context}.items must be an array schema.");
        }

        foreach ($schema as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            if (array_is_list($value)) {
                foreach ($value as $index => $child) {
                    if (is_array($child)) {
                        $this->assertArraySchemaHasItemsForArrayTypes($child, "{$context}.{$key}.{$index}");
                    }
                }

                continue;
            }

            $this->assertArraySchemaHasItemsForArrayTypes($value, "{$context}.{$key}");
        }
    }
}
