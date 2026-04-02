import { Test, TestingModule } from '@nestjs/testing';
import { BillingWebhookController } from './billing-webhook.controller';
import { BillingWebhookService } from '../services/billing-webhook.service';
import { IdempotentWebhookGuard } from '../../../shared/guards/idempotent-webhook.guard';
import { IdempotencyService } from '../../../shared/services/idempotency';

describe('BillingWebhookController', () => {
  let controller: BillingWebhookController;
  let billingWebhookService: jest.Mocked<BillingWebhookService>;

  beforeEach(async () => {
    const mockBillingWebhookService = {
      handle: jest.fn(),
    };

    const module: TestingModule = await Test.createTestingModule({
      controllers: [BillingWebhookController],
      providers: [
        {
          provide: BillingWebhookService,
          useValue: mockBillingWebhookService,
        },
        {
          provide: IdempotentWebhookGuard,
          useValue: {
            canActivate: jest.fn().mockReturnValue(true),
          },
        },
        {
          provide: IdempotencyService,
          useValue: {
            check: jest.fn().mockResolvedValue({ isDuplicate: false }),
            markProcessed: jest.fn().mockResolvedValue(undefined),
          },
        },
      ],
    }).compile();

    controller = module.get<BillingWebhookController>(BillingWebhookController);
    billingWebhookService = module.get(BillingWebhookService);
  });

  describe('handle', () => {
    it('should handle billing webhook successfully', async () => {
      const payload = {
        event: 'PAYMENT_RECEIVED',
        payment: { id: 'pay-1', value: 100 },
      };

      billingWebhookService.handle.mockResolvedValue(undefined);

      const result = await controller.handle('asaas', 'token-123', payload);

      expect(result).toEqual({ success: true });
      expect(billingWebhookService.handle).toHaveBeenCalledWith(
        'asaas',
        'token-123',
        payload,
      );
    });

    it('should handle different event types', async () => {
      const payload = {
        event: 'PAYMENT_CONFIRMED',
        payment: { id: 'pay-2', value: 200 },
      };

      billingWebhookService.handle.mockResolvedValue(undefined);

      await controller.handle('asaas', 'token-456', payload);

      expect(billingWebhookService.handle).toHaveBeenCalled();
    });

    it('should handle complex payloads', async () => {
      const payload = {
        event: 'PAYMENT_RECEIVED',
        payment: {
          id: 'pay-3',
          value: 150.5,
          customer: { id: 'cus-1', name: 'Test' },
          metadata: { order_id: 'ord-1' },
        },
      };

      billingWebhookService.handle.mockResolvedValue(undefined);

      await controller.handle('asaas', 'token-789', payload);

      expect(billingWebhookService.handle).toHaveBeenCalledWith(
        'asaas',
        'token-789',
        payload,
      );
    });
  });
});
