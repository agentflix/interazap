import 'reflect-metadata';
import { validate } from 'class-validator';
import { plainToInstance } from 'class-transformer';
import { OutboundMessageDto } from './outbound-message.dto';

describe('OutboundMessageDto', () => {
  const validPayload = {
    provider: 'uazapi',
    instanceToken: 'token-123',
    tenantId: 'tenant-456',
    instanceId: 'instance-789',
    type: 'text',
    to: '5511999999999',
    text: 'Hello!',
  };

  describe('validation', () => {
    it('should validate a valid text message', async () => {
      const dto = plainToInstance(OutboundMessageDto, validPayload);
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
    });

    it('should validate a valid media message', async () => {
      const dto = plainToInstance(OutboundMessageDto, {
        ...validPayload,
        type: 'media',
        mediaType: 'image',
        mediaUrl: 'https://example.com/image.jpg',
        caption: 'My image',
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
    });

    it('should validate with zapi provider', async () => {
      const dto = plainToInstance(OutboundMessageDto, {
        ...validPayload,
        provider: 'zapi',
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
    });

    it('should fail with invalid provider', async () => {
      const dto = plainToInstance(OutboundMessageDto, {
        ...validPayload,
        provider: 'invalid',
      });
      const errors = await validate(dto);
      expect(errors.length).toBeGreaterThan(0);
    });

    it('should fail with invalid type', async () => {
      const dto = plainToInstance(OutboundMessageDto, {
        ...validPayload,
        type: 'invalid',
      });
      const errors = await validate(dto);
      expect(errors.length).toBeGreaterThan(0);
    });

    it('should fail without required fields', async () => {
      const dto = plainToInstance(OutboundMessageDto, {});
      const errors = await validate(dto);
      expect(errors.length).toBeGreaterThan(0);
    });
  });

  describe('Transform decorators', () => {
    it('should transform instanceToken from instance_token', () => {
      const dto = plainToInstance(OutboundMessageDto, {
        ...validPayload,
        instanceToken: undefined,
        instance_token: 'fallback-token',
      });
      expect(dto.instanceToken).toBe('fallback-token');
    });

    it('should prefer camelCase over snake_case for instanceToken', () => {
      const dto = plainToInstance(OutboundMessageDto, {
        ...validPayload,
        instanceToken: 'camel-token',
        instance_token: 'snake-token',
      });
      expect(dto.instanceToken).toBe('camel-token');
    });

    it('should transform tenantId from tenant_id', () => {
      const dto = plainToInstance(OutboundMessageDto, {
        ...validPayload,
        tenantId: undefined,
        tenant_id: 'fallback-tenant',
      });
      expect(dto.tenantId).toBe('fallback-tenant');
    });

    it('should prefer camelCase over snake_case for tenantId', () => {
      const dto = plainToInstance(OutboundMessageDto, {
        ...validPayload,
        tenantId: 'camel-tenant',
        tenant_id: 'snake-tenant',
      });
      expect(dto.tenantId).toBe('camel-tenant');
    });

    it('should transform instanceId from instance_id', () => {
      const dto = plainToInstance(OutboundMessageDto, {
        ...validPayload,
        instanceId: undefined,
        instance_id: 'fallback-instance',
      });
      expect(dto.instanceId).toBe('fallback-instance');
    });

    it('should prefer camelCase over snake_case for instanceId', () => {
      const dto = plainToInstance(OutboundMessageDto, {
        ...validPayload,
        instanceId: 'camel-instance',
        instance_id: 'snake-instance',
      });
      expect(dto.instanceId).toBe('camel-instance');
    });

    it('should transform mediaType from media_type', () => {
      const dto = plainToInstance(OutboundMessageDto, {
        ...validPayload,
        type: 'media',
        mediaType: undefined,
        media_type: 'video',
        mediaUrl: 'https://example.com/video.mp4',
      });
      expect(dto.mediaType).toBe('video');
    });

    it('should prefer camelCase over snake_case for mediaType', () => {
      const dto = plainToInstance(OutboundMessageDto, {
        ...validPayload,
        type: 'media',
        mediaType: 'image',
        media_type: 'video',
        mediaUrl: 'https://example.com/media.jpg',
      });
      expect(dto.mediaType).toBe('image');
    });

    it('should transform mediaUrl from media_url', () => {
      const dto = plainToInstance(OutboundMessageDto, {
        ...validPayload,
        type: 'media',
        mediaType: 'image',
        mediaUrl: undefined,
        media_url: 'https://example.com/fallback.jpg',
      });
      expect(dto.mediaUrl).toBe('https://example.com/fallback.jpg');
    });

    it('should prefer camelCase over snake_case for mediaUrl', () => {
      const dto = plainToInstance(OutboundMessageDto, {
        ...validPayload,
        type: 'media',
        mediaType: 'image',
        mediaUrl: 'https://example.com/camel.jpg',
        media_url: 'https://example.com/snake.jpg',
      });
      expect(dto.mediaUrl).toBe('https://example.com/camel.jpg');
    });

    it('should transform fileName from file_name', () => {
      const dto = plainToInstance(OutboundMessageDto, {
        ...validPayload,
        type: 'media',
        mediaType: 'document',
        mediaUrl: 'https://example.com/doc.pdf',
        fileName: undefined,
        file_name: 'report.pdf',
      });
      expect(dto.fileName).toBe('report.pdf');
    });

    it('should prefer camelCase over snake_case for fileName', () => {
      const dto = plainToInstance(OutboundMessageDto, {
        ...validPayload,
        type: 'media',
        mediaType: 'document',
        mediaUrl: 'https://example.com/doc.pdf',
        fileName: 'camel.pdf',
        file_name: 'snake.pdf',
      });
      expect(dto.fileName).toBe('camel.pdf');
    });

    it('should transform correlationId from correlation_id', () => {
      const dto = plainToInstance(OutboundMessageDto, {
        ...validPayload,
        correlationId: undefined,
        correlation_id: 'fallback-corr-id',
      });
      expect(dto.correlationId).toBe('fallback-corr-id');
    });

    it('should prefer camelCase over snake_case for correlationId', () => {
      const dto = plainToInstance(OutboundMessageDto, {
        ...validPayload,
        correlationId: 'camel-corr-id',
        correlation_id: 'snake-corr-id',
      });
      expect(dto.correlationId).toBe('camel-corr-id');
    });

    it('should handle undefined obj in transform', () => {
      // Test edge case where obj might be undefined
      const dto = plainToInstance(OutboundMessageDto, {
        provider: 'uazapi',
        type: 'text',
        to: '5511999999999',
        text: 'Hello',
        // Missing other fields to test undefined fallback behavior
      });
      // Should not throw, should handle gracefully
      expect(dto.provider).toBe('uazapi');
    });

    it('should return undefined when fallback is not a string', () => {
      const dto = plainToInstance(OutboundMessageDto, {
        ...validPayload,
        instanceToken: undefined,
        instance_token: 123, // Not a string
      });
      expect(dto.instanceToken).toBeUndefined();
    });
  });
});
