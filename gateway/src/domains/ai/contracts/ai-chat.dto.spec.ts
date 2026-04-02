import 'reflect-metadata';
import { validate } from 'class-validator';
import { plainToInstance } from 'class-transformer';
import {
  ChatMessageDto,
  AiChatCommandDto,
  AiChatResultDto,
} from './ai-chat.dto';

describe('ChatMessageDto', () => {
  it('should validate a valid message', async () => {
    const dto = plainToInstance(ChatMessageDto, {
      role: 'user',
      content: 'Hello, world!',
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should validate all roles', async () => {
    const roles = ['system', 'user', 'assistant', 'function'] as const;
    for (const role of roles) {
      const dto = plainToInstance(ChatMessageDto, { role, content: 'test' });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
    }
  });

  it('should accept optional name', async () => {
    const dto = plainToInstance(ChatMessageDto, {
      role: 'user',
      content: 'Hello',
      name: 'John',
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should fail without content', async () => {
    const dto = plainToInstance(ChatMessageDto, { role: 'user' });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail without role', async () => {
    const dto = plainToInstance(ChatMessageDto, { content: 'test' });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });
});

describe('AiChatCommandDto', () => {
  const validPayload = {
    tenantId: 'tenant-123',
    correlationId: 'corr-456',
    messages: [{ role: 'user', content: 'Hello' }],
  };

  it('should validate a valid command', async () => {
    const dto = plainToInstance(AiChatCommandDto, validPayload);
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should accept optional model', async () => {
    const dto = plainToInstance(AiChatCommandDto, {
      ...validPayload,
      model: 'gpt-4o',
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should accept optional temperature', async () => {
    const dto = plainToInstance(AiChatCommandDto, {
      ...validPayload,
      temperature: 0.7,
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should accept optional maxTokens', async () => {
    const dto = plainToInstance(AiChatCommandDto, {
      ...validPayload,
      maxTokens: 1000,
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should accept optional metadata', async () => {
    const dto = plainToInstance(AiChatCommandDto, {
      ...validPayload,
      metadata: { key: 'value' },
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should fail without tenantId', async () => {
    const dto = plainToInstance(AiChatCommandDto, {
      correlationId: 'corr-456',
      messages: [{ role: 'user', content: 'Hello' }],
    });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail without correlationId', async () => {
    const dto = plainToInstance(AiChatCommandDto, {
      tenantId: 'tenant-123',
      messages: [{ role: 'user', content: 'Hello' }],
    });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail without messages', async () => {
    const dto = plainToInstance(AiChatCommandDto, {
      tenantId: 'tenant-123',
      correlationId: 'corr-456',
    });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });
});

describe('AiChatResultDto', () => {
  const validPayload = {
    correlationId: 'corr-123',
    tenantId: 'tenant-456',
    success: true,
  };

  it('should validate a valid result', async () => {
    const dto = plainToInstance(AiChatResultDto, validPayload);
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should accept all optional fields', async () => {
    const dto = plainToInstance(AiChatResultDto, {
      ...validPayload,
      content: 'AI response',
      finishReason: 'stop',
      promptTokens: 10,
      completionTokens: 20,
      totalTokens: 30,
      processingTimeMs: 150,
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should accept error for failed result', async () => {
    const dto = plainToInstance(AiChatResultDto, {
      correlationId: 'corr-123',
      tenantId: 'tenant-456',
      success: false,
      error: 'Rate limit exceeded',
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should fail without correlationId', async () => {
    const dto = plainToInstance(AiChatResultDto, {
      tenantId: 'tenant-456',
      success: true,
    });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail without tenantId', async () => {
    const dto = plainToInstance(AiChatResultDto, {
      correlationId: 'corr-123',
      success: true,
    });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail without success', async () => {
    const dto = plainToInstance(AiChatResultDto, {
      correlationId: 'corr-123',
      tenantId: 'tenant-456',
    });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });
});
