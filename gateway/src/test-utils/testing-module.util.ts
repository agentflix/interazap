import { ModuleMetadata } from '@nestjs/common';
import { Test, TestingModule } from '@nestjs/testing';

type TestingModuleOptions = {
  providers: NonNullable<ModuleMetadata['providers']>;
  imports?: NonNullable<ModuleMetadata['imports']>;
};

/**
 * Builds a Nest testing module with shared defaults.
 */
export async function buildTestingModule(
  options: TestingModuleOptions,
): Promise<TestingModule> {
  const builder = Test.createTestingModule({
    imports: options.imports ?? [],
    providers: options.providers,
  });

  return builder.compile();
}
