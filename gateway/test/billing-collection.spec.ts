import { Test, TestingModule } from '@nestjs/testing';
import { BillingCollectionController } from '../src/domains/billing/controllers/billing-collection.controller';
import { BillingCollectionService } from '../src/domains/billing/services/billing-collection.service';
import { InternalApiKeyGuard } from '../src/domains/realtime/guards/internal-api-key.guard';
import { ConfigService } from '@nestjs/config';

describe('BillingCollectionController', () => {
  let controller: BillingCollectionController;
  let service: { send: jest.Mock };

  beforeEach(async () => {
    service = {
      send: jest.fn(),
    };

    const module: TestingModule = await Test.createTestingModule({
      controllers: [BillingCollectionController],
      providers: [
        { provide: BillingCollectionService, useValue: service },
        InternalApiKeyGuard,
        {
          provide: ConfigService,
          useValue: { get: jest.fn().mockReturnValue('test-key') },
        },
      ],
    }).compile();

    controller = module.get<BillingCollectionController>(
      BillingCollectionController,
    );
  });

  it('sends billing collection message via service', async () => {
    service.send.mockResolvedValue({
      success: true,
      messageId: 'msg-1',
      error: null,
    });

    const result = await controller.send({
      tenantId: 'tenant-1',
      phone: '+55 11 99999-1111',
      templateId: 'friendly_reminder',
      variables: {
        tenant_name: 'ACME',
        payment_url: 'https://pay.example.com/inv-1',
      },
    });

    expect(service.send).toHaveBeenCalledWith(
      'tenant-1',
      '+55 11 99999-1111',
      'friendly_reminder',
      {
        tenant_name: 'ACME',
        payment_url: 'https://pay.example.com/inv-1',
      },
    );

    expect(result).toEqual({
      success: true,
      messageId: 'msg-1',
      error: null,
    });
  });
});
