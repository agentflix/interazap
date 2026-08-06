import { Test, TestingModule } from '@nestjs/testing';
import { MetaWebhookQueueService } from './meta-webhook-queue.service';
import { MetaWebhookProcessor } from '../processors/meta-webhook.processor';
import { BullMQQueueFactory } from '../../../shared/services/queue/bullmq-queue-factory.service';

describe('MetaWebhookQueueService', () => {
  let service: MetaWebhookQueueService;
  let queueFactory: {
    createQueue: jest.Mock;
    createWorker: jest.Mock;
  };
  let processor: { process: jest.Mock };
  let addFn: jest.Mock;

  beforeEach(async () => {
    addFn = jest.fn().mockResolvedValue('job-id');
    queueFactory = {
      createQueue: jest.fn().mockReturnValue({ add: addFn }),
      createWorker: jest.fn().mockReturnValue({ close: jest.fn() }),
    };
    processor = { process: jest.fn().mockResolvedValue(undefined) };

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        MetaWebhookQueueService,
        { provide: BullMQQueueFactory, useValue: queueFactory },
        { provide: MetaWebhookProcessor, useValue: processor },
      ],
    }).compile();

    service = module.get<MetaWebhookQueueService>(MetaWebhookQueueService);
  });

  it('creates queue and worker lazily and enqueues payload durably', async () => {
    const payload = {
      object: 'whatsapp_business_account',
      entry: [{ id: 'WABA_1', changes: [] }],
    };

    await service.enqueue(payload);

    expect(queueFactory.createQueue).toHaveBeenCalledWith(
      'meta-webhook-events',
    );
    expect(queueFactory.createWorker).toHaveBeenCalledWith(
      'meta-webhook-events',
      expect.any(Function),
    );
    expect(addFn).toHaveBeenCalledWith('process-meta-webhook', {
      payload,
      receivedAt: expect.any(String),
    });
  });

  it('reuses the same queue instance across enqueues', async () => {
    await service.enqueue({ object: 'whatsapp_business_account', entry: [] });
    await service.enqueue({ object: 'whatsapp_business_account', entry: [] });

    expect(queueFactory.createQueue).toHaveBeenCalledTimes(1);
    expect(queueFactory.createWorker).toHaveBeenCalledTimes(1);
    expect(addFn).toHaveBeenCalledTimes(2);
  });

  it('propagates enqueue failure (no false ACK by caller)', async () => {
    addFn.mockRejectedValue(new Error('redis down'));

    await expect(
      service.enqueue({ object: 'whatsapp_business_account', entry: [] }),
    ).rejects.toThrow('redis down');
  });

  it('wires the worker to the processor', async () => {
    await service.enqueue({ object: 'whatsapp_business_account', entry: [] });

    const workerProcessor = queueFactory.createWorker.mock.calls[0][1];
    const fakeJob = {
      data: { payload: { entry: [] }, receivedAt: '2026-08-05T00:00:00Z' },
    };

    await workerProcessor(fakeJob);

    expect(processor.process).toHaveBeenCalledWith(fakeJob.data);
  });
});
