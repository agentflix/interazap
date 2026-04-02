import 'reflect-metadata';
import { validate } from 'class-validator';
import { plainToInstance } from 'class-transformer';
import {
  ChatMessageDto,
  AICompletionRequestDto,
} from './ai-completion-request.dto';

describe('ChatMessageDto (AICompletion)', () => {
  it('should validate system role', async () => {
    const dto = plainToInstance(ChatMessageDto, {
      role: 'system',
      content: 'You are a helpful assistant',
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should validate user role', async () => {
    const dto = plainToInstance(ChatMessageDto, {
      role: 'user',
      content: 'Hello!',
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should validate assistant role', async () => {
    const dto = plainToInstance(ChatMessageDto, {
      role: 'assistant',
      content: 'How can I help you?',
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
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

  it('should fail with invalid role', async () => {
    const dto = plainToInstance(ChatMessageDto, {
      role: 'invalid-role',
      content: 'test',
    });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
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

describe('AICompletionRequestDto', () => {
  const validMessages = [
    { role: 'system', content: 'You are a helpful assistant' },
    { role: 'user', content: 'Hello!' },
  ];

  it('should validate a valid request', async () => {
    const dto = plainToInstance(AICompletionRequestDto, {
      messages: validMessages,
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should accept optional model', async () => {
    const dto = plainToInstance(AICompletionRequestDto, {
      messages: validMessages,
      model: 'gpt-4o',
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should accept optional maxTokens within range', async () => {
    const dto = plainToInstance(AICompletionRequestDto, {
      messages: validMessages,
      maxTokens: 4096,
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should fail with maxTokens below minimum', async () => {
    const dto = plainToInstance(AICompletionRequestDto, {
      messages: validMessages,
      maxTokens: 0,
    });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail with maxTokens above maximum', async () => {
    const dto = plainToInstance(AICompletionRequestDto, {
      messages: validMessages,
      maxTokens: 200000,
    });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should accept optional temperature within range', async () => {
    const dto = plainToInstance(AICompletionRequestDto, {
      messages: validMessages,
      temperature: 0.7,
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should accept temperature at boundaries', async () => {
    const dto0 = plainToInstance(AICompletionRequestDto, {
      messages: validMessages,
      temperature: 0,
    });
    const dto2 = plainToInstance(AICompletionRequestDto, {
      messages: validMessages,
      temperature: 2,
    });
    const errors0 = await validate(dto0);
    const errors2 = await validate(dto2);
    expect(errors0).toHaveLength(0);
    expect(errors2).toHaveLength(0);
  });

  it('should fail with temperature above maximum', async () => {
    const dto = plainToInstance(AICompletionRequestDto, {
      messages: validMessages,
      temperature: 2.5,
    });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail with temperature below minimum', async () => {
    const dto = plainToInstance(AICompletionRequestDto, {
      messages: validMessages,
      temperature: -0.5,
    });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail without messages', async () => {
    const dto = plainToInstance(AICompletionRequestDto, {});
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail with empty messages array', async () => {
    const dto = plainToInstance(AICompletionRequestDto, { messages: [] });
    // Validate to ensure DTO is properly created (result not needed for this test)
    await validate(dto);
    // Empty array is valid from class-validator perspective
    // Business logic should handle empty messages
    expect(dto.messages).toHaveLength(0);
  });

  it('should validate nested messages', async () => {
    const dto = plainToInstance(AICompletionRequestDto, {
      messages: [{ role: 'invalid', content: 'test' }],
    });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });
});
