import 'reflect-metadata';
import { validate } from 'class-validator';
import { plainToInstance } from 'class-transformer';
import {
  AiEmbeddingCommandDto,
  EmbeddingResultDto,
  AiEmbeddingResultDto,
} from './ai-embedding.dto';

describe('AiEmbeddingCommandDto', () => {
  const validPayload = {
    tenantId: 'tenant-123',
    correlationId: 'corr-456',
    texts: ['Hello, world!', 'Another text'],
  };

  it('should validate a valid command', async () => {
    const dto = plainToInstance(AiEmbeddingCommandDto, validPayload);
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should accept optional model', async () => {
    const dto = plainToInstance(AiEmbeddingCommandDto, {
      ...validPayload,
      model: 'text-embedding-3-small',
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should fail without tenantId', async () => {
    const dto = plainToInstance(AiEmbeddingCommandDto, {
      correlationId: 'corr-456',
      texts: ['Hello'],
    });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail without correlationId', async () => {
    const dto = plainToInstance(AiEmbeddingCommandDto, {
      tenantId: 'tenant-123',
      texts: ['Hello'],
    });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail without texts', async () => {
    const dto = plainToInstance(AiEmbeddingCommandDto, {
      tenantId: 'tenant-123',
      correlationId: 'corr-456',
    });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail with non-string texts', async () => {
    const dto = plainToInstance(AiEmbeddingCommandDto, {
      tenantId: 'tenant-123',
      correlationId: 'corr-456',
      texts: [123, 456],
    });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });
});

describe('EmbeddingResultDto', () => {
  it('should validate a valid embedding result', async () => {
    const dto = plainToInstance(EmbeddingResultDto, {
      index: 0,
      embedding: [0.1, 0.2, 0.3, 0.4],
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should fail without index', async () => {
    const dto = plainToInstance(EmbeddingResultDto, {
      embedding: [0.1, 0.2, 0.3],
    });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail without embedding', async () => {
    const dto = plainToInstance(EmbeddingResultDto, { index: 0 });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });
});

describe('AiEmbeddingResultDto', () => {
  const validPayload = {
    correlationId: 'corr-123',
    tenantId: 'tenant-456',
    success: true,
  };

  it('should validate a valid result', async () => {
    const dto = plainToInstance(AiEmbeddingResultDto, validPayload);
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should accept all optional fields', async () => {
    const dto = plainToInstance(AiEmbeddingResultDto, {
      ...validPayload,
      embeddings: [{ index: 0, embedding: [0.1, 0.2, 0.3] }],
      totalTokens: 100,
      processingTimeMs: 50,
      model: 'text-embedding-3-small',
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should accept error for failed result', async () => {
    const dto = plainToInstance(AiEmbeddingResultDto, {
      correlationId: 'corr-123',
      tenantId: 'tenant-456',
      success: false,
      error: 'Invalid input',
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should fail without correlationId', async () => {
    const dto = plainToInstance(AiEmbeddingResultDto, {
      tenantId: 'tenant-456',
      success: true,
    });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail without tenantId', async () => {
    const dto = plainToInstance(AiEmbeddingResultDto, {
      correlationId: 'corr-123',
      success: true,
    });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail without success', async () => {
    const dto = plainToInstance(AiEmbeddingResultDto, {
      correlationId: 'corr-123',
      tenantId: 'tenant-456',
    });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });
});
