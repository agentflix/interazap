import { ToolStrategyRegistry } from './tool-strategy.registry';
import { ToolStrategy } from './tool-strategy.interface';
import { ToolStrategyRuntime } from './tool-strategy.types';
import { ToolExecutionContext } from '../../interfaces/tool-execution-context.interface';

class DefaultStrategyMock implements ToolStrategy {
  readonly name = '*';

  execute(
    name: string,
    args: Record<string, unknown>,
    context: ToolExecutionContext,
    runtime: ToolStrategyRuntime,
  ): Promise<Record<string, unknown>> {
    void name;
    void args;
    void context;
    void runtime;
    return Promise.resolve({ fallback: true });
  }
}

class NamedStrategyMock implements ToolStrategy {
  constructor(readonly name: string) {}

  execute(
    name: string,
    args: Record<string, unknown>,
    context: ToolExecutionContext,
    runtime: ToolStrategyRuntime,
  ): Promise<Record<string, unknown>> {
    void name;
    void args;
    void context;
    void runtime;
    return Promise.resolve({ name: this.name });
  }
}

describe('ToolStrategyRegistry', () => {
  it('resolves built-in strategies and falls back for unknown tools', () => {
    const registry = ToolStrategyRegistry.createDefault();

    const sendMessage = registry.resolve('send_message');
    const unknown = registry.resolve('totally_unknown_tool');

    expect(sendMessage.name).toBe('send_message');
    expect(unknown.name).toBe('*');
  });

  it('rejects duplicate strategy registration', () => {
    const registry = new ToolStrategyRegistry(new DefaultStrategyMock(), [
      new NamedStrategyMock('tool_a'),
    ]);

    expect(() => registry.register(new NamedStrategyMock('tool_a'))).toThrow(
      'Duplicate tool strategy registration: tool_a',
    );
  });
});
