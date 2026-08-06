import { Test, TestingModule } from '@nestjs/testing';
import { MetaWebhookProcessor } from './meta-webhook.processor';
import { MetaAdapter } from '../providers/meta/meta.adapter';
import { ChatWebhookService } from '../services/chat-webhook.service';

describe('MetaWebhookProcessor', () => {
  let processor: MetaWebhookProcessor;
  let metaAdapter: { normalizeWebhookBatch: jest.Mock };
  let chatWebhookService: { handleNormalizedEvents: jest.Mock };

  beforeEach(async () => {
    metaAdapter = { normalizeWebhookBatch: jest.fn() };
    chatWebhookService = { handleNormalizedEvents: jest.fn() };

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        MetaWebhookProcessor,
        { provide: MetaAdapter, useValue: metaAdapter },
        { provide: ChatWebhookService, useValue: chatWebhookService },
      ],
    }).compile();

    processor = module.get<MetaWebhookProcessor>(MetaWebhookProcessor);
  });

  it('normalizes the batch and delegates events to the chat service', async () => {
    const event = {
      tenantId: 'tenant-1',
      instanceId: 'inst-1',
      provider: 'meta',
      eventType: 'messages',
      direction: 'inbound',
      rawPayload: {},
      idempotencyKey: 'meta:inst-1:wamid.X',
      receivedAt: new Date(),
    };
    metaAdapter.normalizeWebhookBatch.mockResolvedValue([event]);

    await processor.process({
      payload: { object: 'whatsapp_business_account', entry: [] },
      receivedAt: '2026-08-05T00:00:00Z',
    });

    expect(metaAdapter.normalizeWebhookBatch).toHaveBeenCalledWith({
      object: 'whatsapp_business_account',
      entry: [],
    });
    expect(chatWebhookService.handleNormalizedEvents).toHaveBeenCalledWith([
      event,
    ]);
  });

  it('does not delegate when no resolvable event is produced', async () => {
    metaAdapter.normalizeWebhookBatch.mockResolvedValue([]);

    await processor.process({
      payload: { object: 'whatsapp_business_account', entry: [] },
      receivedAt: '2026-08-05T00:00:00Z',
    });

    expect(chatWebhookService.handleNormalizedEvents).not.toHaveBeenCalled();
  });

  it('propagates adapter failure so BullMQ retries/DLQ the job', async () => {
    metaAdapter.normalizeWebhookBatch.mockRejectedValue(
      new Error('graph down'),
    );

    await expect(
      processor.process({
        payload: { object: 'whatsapp_business_account', entry: [] },
        receivedAt: '2026-08-05T00:00:00Z',
      }),
    ).rejects.toThrow('graph down');
  });
});
