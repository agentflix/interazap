import 'reflect-metadata';
import { validate } from 'class-validator';
import { plainToInstance } from 'class-transformer';
import { InstanceConfigDto } from './instance-config.dto';

describe('InstanceConfigDto', () => {
  const validPayload = {
    id: 'instance-123',
    tenantId: 'tenant-456',
    provider: 'uazapi',
    webhookToken: 'token-789',
  };

  it('should validate a valid uazapi config', async () => {
    const dto = plainToInstance(InstanceConfigDto, validPayload);
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
    expect(dto.id).toBe('instance-123');
    expect(dto.tenantId).toBe('tenant-456');
    expect(dto.provider).toBe('uazapi');
  });

  it('should validate a valid zapi config', async () => {
    const dto = plainToInstance(InstanceConfigDto, {
      ...validPayload,
      provider: 'zapi',
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should accept optional instanceToken', async () => {
    const dto = plainToInstance(InstanceConfigDto, {
      ...validPayload,
      instanceToken: 'instance-token-xyz',
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
    expect(dto.instanceToken).toBe('instance-token-xyz');
  });

  it('should accept optional phone', async () => {
    const dto = plainToInstance(InstanceConfigDto, {
      ...validPayload,
      phone: '+5511999999999',
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
    expect(dto.phone).toBe('+5511999999999');
  });

  it('should accept optional settings', async () => {
    const dto = plainToInstance(InstanceConfigDto, {
      ...validPayload,
      settings: { autoReply: true, timeout: 30 },
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
    expect(dto.settings).toEqual({ autoReply: true, timeout: 30 });
  });

  it('should fail without id', async () => {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { id: _id, ...payload } = validPayload;
    const dto = plainToInstance(InstanceConfigDto, payload);
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail without tenantId', async () => {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { tenantId: _tenantId, ...payload } = validPayload;
    const dto = plainToInstance(InstanceConfigDto, payload);
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail without provider', async () => {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { provider: _provider, ...payload } = validPayload;
    const dto = plainToInstance(InstanceConfigDto, payload);
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail with invalid provider', async () => {
    const dto = plainToInstance(InstanceConfigDto, {
      ...validPayload,
      provider: 'invalid-provider',
    });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail without webhookToken', async () => {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { webhookToken: _webhookToken, ...payload } = validPayload;
    const dto = plainToInstance(InstanceConfigDto, payload);
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });
});
