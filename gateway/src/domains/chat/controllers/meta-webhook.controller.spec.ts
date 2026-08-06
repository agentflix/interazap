import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import {
  ForbiddenException,
  InternalServerErrorException,
} from '@nestjs/common';
import * as crypto from 'crypto';
import { MetaWebhookController } from './meta-webhook.controller';
import { MetaAdapter } from '../providers/meta/meta.adapter';
import { MetaConfigService } from '../providers/meta/meta.config';
import { MetaWebhookQueueService } from '../services/meta-webhook-queue.service';

describe('MetaWebhookController', () => {
  let controller: MetaWebhookController;
  let metaConfig: { isConfigured: jest.Mock };
  let configService: { get: jest.Mock };
  let metaAdapter: { normalizeWebhookBatch: jest.Mock };
  let queueService: { enqueue: jest.Mock };

  const configuredMetaConfig = () => {
    metaConfig.isConfigured.mockReturnValue(true);
  };

  const unconfiguredMetaConfig = () => {
    metaConfig.isConfigured.mockReturnValue(false);
  };

  const sign = (rawBody: string): string =>
    `sha256=${crypto
      .createHmac('sha256', 'app-secret')
      .update(rawBody)
      .digest('hex')}`;

  beforeEach(async () => {
    metaConfig = { isConfigured: jest.fn() };
    configService = { get: jest.fn() };
    metaAdapter = { normalizeWebhookBatch: jest.fn() };
    queueService = { enqueue: jest.fn() };

    const module: TestingModule = await Test.createTestingModule({
      controllers: [MetaWebhookController],
      providers: [
        { provide: ConfigService, useValue: configService },
        { provide: MetaAdapter, useValue: metaAdapter },
        { provide: MetaConfigService, useValue: metaConfig },
        { provide: MetaWebhookQueueService, useValue: queueService },
      ],
    }).compile();

    controller = module.get<MetaWebhookController>(MetaWebhookController);
  });

  describe('verifyWebhook (handshake)', () => {
    it('rejects handshake when META_VERIFY_TOKEN/META_APP_SECRET are missing (fail-closed)', () => {
      unconfiguredMetaConfig();

      expect(() =>
        controller.verifyWebhook('subscribe', 'anything', 'challenge-1'),
      ).toThrow(ForbiddenException);
      expect(configService.get).not.toHaveBeenCalled();
    });

    it('returns challenge when configured and token matches', () => {
      configuredMetaConfig();
      configService.get.mockReturnValue('verify-token-123');

      const result = controller.verifyWebhook(
        'subscribe',
        'verify-token-123',
        'challenge-abc',
      );

      expect(result).toBe('challenge-abc');
    });

    it('throws when configured but token mismatches', () => {
      configuredMetaConfig();
      configService.get.mockReturnValue('verify-token-123');

      expect(() =>
        controller.verifyWebhook('subscribe', 'wrong-token', 'challenge-abc'),
      ).toThrow(ForbiddenException);
    });
  });

  describe('handleWebhook (POST)', () => {
    const payload = {
      object: 'whatsapp_business_account',
      entry: [
        {
          id: 'WABA_1',
          changes: [
            {
              field: 'messages',
              value: {
                messaging_product: 'whatsapp',
                metadata: { phone_number_id: 'PHN_1' },
                messages: [{ id: 'wamid.X' }],
              },
            },
          ],
        },
      ],
    };
    const rawBody = Buffer.from(JSON.stringify(payload));
    const req = { rawBody } as never;

    it('rejects webhook when META_APP_SECRET is missing — never validates with empty key', async () => {
      unconfiguredMetaConfig();

      await expect(
        controller.handleWebhook(
          'sha256=any',
          payload,
          req,
        ),
      ).rejects.toThrow(ForbiddenException);
      expect(queueService.enqueue).not.toHaveBeenCalled();
      expect(metaAdapter.normalizeWebhookBatch).not.toHaveBeenCalled();
    });

    it('rejects with 403 when signature invalid — no lookup, no enqueue', async () => {
      configuredMetaConfig();
      configService.get.mockReturnValue('app-secret');

      await expect(
        controller.handleWebhook(
          'sha256=invalid-signature',
          payload,
          req,
        ),
      ).rejects.toThrow(ForbiddenException);
      expect(queueService.enqueue).not.toHaveBeenCalled();
      expect(metaAdapter.normalizeWebhookBatch).not.toHaveBeenCalled();
    });

    it('acks 200 without processing when payload lacks minimal shape', async () => {
      configuredMetaConfig();
      configService.get.mockReturnValue('app-secret');
      const malformed = { object: 'whatsapp_business_account' };
      const malformedRaw = Buffer.from(JSON.stringify(malformed));

      const result = await controller.handleWebhook(
        sign(malformedRaw.toString()),
        malformed,
        { rawBody: malformedRaw } as never,
      );

      expect(result).toEqual({ success: true });
      expect(queueService.enqueue).not.toHaveBeenCalled();
      expect(metaAdapter.normalizeWebhookBatch).not.toHaveBeenCalled();
    });

    it('acks 200 only AFTER durable enqueue — never calls lookup/normalization inline', async () => {
      configuredMetaConfig();
      configService.get.mockReturnValue('app-secret');
      queueService.enqueue.mockResolvedValue(undefined);

      const result = await controller.handleWebhook(
        sign(rawBody.toString()),
        payload,
        req,
      );

      expect(result).toEqual({ success: true });
      expect(queueService.enqueue).toHaveBeenCalledTimes(1);
      expect(queueService.enqueue).toHaveBeenCalledWith(payload);
      // O ACK NÃO depende de lookup HTTP nem de processamento inline
      expect(metaAdapter.normalizeWebhookBatch).not.toHaveBeenCalled();
    });

    it('throws 500 and does NOT ack when enqueue fails (no false ACK)', async () => {
      configuredMetaConfig();
      configService.get.mockReturnValue('app-secret');
      queueService.enqueue.mockRejectedValue(new Error('redis down'));

      await expect(
        controller.handleWebhook(sign(rawBody.toString()), payload, req),
      ).rejects.toThrow(InternalServerErrorException);
      expect(metaAdapter.normalizeWebhookBatch).not.toHaveBeenCalled();
    });
  });
});
